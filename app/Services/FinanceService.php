<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\BankAccount;
use App\Models\Charge;
use App\Models\Expense;
use App\Models\FinancialPeriod;
use App\Models\FundOpening;
use App\Models\FundUse;
use App\Models\Payment;
use App\Models\PeriodItem;
use App\Models\Property;
use App\Models\PropertyCredit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public const CATEGORIES = ['sector_fixed', 'sector_variable', 'common_fixed', 'common_variable'];

    public const MAX_AMOUNT = 9000000000000;

    public function setOpeningBalance(Property $property, int $signedUsdCents, string $date, int $actorId): void
    {
        Validator::make(compact('date', 'signedUsdCents'), [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'signedUsdCents' => ['integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
        ])->validate();

        DB::transaction(function () use ($property, $signedUsdCents, $date, $actorId): void {
            $this->lockFunds();
            $property = Property::query()->lockForUpdate()->findOrFail($property->id);
            if ($property->opening_date !== null || $property->charges()->exists() || $property->credits()->exists()) {
                $this->fail('opening_balance', 'El saldo de apertura ya fue registrado o existen movimientos.');
            }
            $property->update(['opening_date' => $date]);
            if ($signedUsdCents > 0) {
                $property->charges()->create([
                    'kind' => 'opening', 'description' => 'Saldo deudor inicial',
                    'charged_on' => $date, 'due_on' => $date,
                    'principal_usd_cents' => $signedUsdCents, 'created_by' => $actorId,
                ]);
            }
            if ($signedUsdCents < 0) {
                $property->credits()->create([
                    'kind' => 'opening', 'amount_usd_cents' => -$signedUsdCents,
                    'credited_on' => $date, 'created_by' => $actorId,
                ]);
            }
        }, 3);
    }

    public function setFundOpening(string $type, int $amountUsdCents, string $date, int $actorId): FundOpening
    {
        Validator::make(compact('type', 'amountUsdCents', 'date'), [
            'type' => ['required', Rule::in(['reserve', 'provision'])],
            'amountUsdCents' => ['required', 'integer', 'between:0,'.self::MAX_AMOUNT],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();

        return DB::transaction(function () use ($type, $amountUsdCents, $date, $actorId): FundOpening {
            $this->lockFunds();
            $opening = FundOpening::query()->where('type', $type)->firstOrFail();
            if ($opening->date !== null || Allocation::query()->exists() || FundUse::query()->exists() || Expense::query()->where('fund', $type)->exists()) {
                $this->fail('opening_balance', 'Registra la apertura del fondo una sola vez, antes de sus movimientos.');
            }
            $opening->update(['amount_usd_cents' => $amountUsdCents, 'date' => $date, 'actor_id' => $actorId]);

            return $opening->refresh();
        }, 3);
    }

    public function issuePeriod(FinancialPeriod $period, int $actorId): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $actorId): FinancialPeriod {
            $this->lockFunds();
            $period = FinancialPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($period->status === 'issued') {
                return $period->load('items', 'charges');
            }
            Validator::make($period->toArray(), [
                'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
                'status' => ['required', Rule::in(['draft'])],
                'reserve_basis_points' => ['integer', 'between:0,10000'],
            ])->validate();
            $items = $period->items()->lockForUpdate()->get();
            if ($items->isEmpty()) {
                $this->fail('items', 'Agrega los conceptos del período antes de emitirlo.');
            }
            $net = 0;
            $provision = 0;
            $provisionUse = 0;
            foreach ($items as $item) {
                Validator::make($item->toArray(), [
                    'category' => ['required', Rule::in(self::CATEGORIES)],
                    'description' => ['required', 'string', 'max:255'],
                    'amount_usd_cents' => ['required', 'integer', 'between:-'.self::MAX_AMOUNT.','.self::MAX_AMOUNT],
                ])->validate();
                $net += $item->amount_usd_cents;
                if ($item->is_provision) {
                    $provision += max(0, $item->amount_usd_cents);
                    $provisionUse += max(0, -$item->amount_usd_cents);
                }
            }
            if ($net < 0 || $provision > $net) {
                $this->fail('items', 'El neto debe ser positivo o cero y cubrir los aportes a provisiones. Revisa los créditos del período.');
            }
            if ($provisionUse > $this->fundBalance('provision')) {
                $this->fail('items', 'El uso de provisiones supera el saldo disponible del fondo.');
            }
            $properties = Property::query()->where('active', true)->orderBy('id')->lockForUpdate()->get();
            if ($properties->count() !== 90) {
                $this->fail('properties', 'La distribución del Sector C requiere los 90 inmuebles activos.');
            }
            $chargedOn = $period->month.'-01';
            $dueOn = $period->due_on?->toDateString() ?? Carbon::parse($chargedOn)->endOfMonth()->toDateString();
            if ($dueOn < $chargedOn) {
                $this->fail('due_on', 'El vencimiento no puede ser anterior al período.');
            }
            foreach ($properties as $property) {
                if ($property->opening_date !== null && $property->opening_date->toDateString() > $chargedOn) {
                    $this->fail('month', 'El período es anterior a la apertura de uno de los inmuebles.');
                }
            }
            $reserve = Money::ratio($net, $period->reserve_basis_points, 10000);
            $total = $net + $reserve;
            $unit = Money::ratio($total, 1, 90);
            $unitReserve = min($unit, Money::ratio($reserve, 1, 90));
            $unitPrincipal = $unit - $unitReserve;
            $unitProvision = min($unitPrincipal, Money::ratio($provision, 1, 90));
            $period->update([
                'status' => 'issued', 'net_usd_cents' => $net,
                'reserve_usd_cents' => $reserve, 'total_usd_cents' => $total,
                'unit_charge_usd_cents' => $unit,
                'rounding_adjustment_usd_cents' => $unit * 90 - $total,
                'units_count' => 90, 'due_on' => $dueOn,
                'items_snapshot' => $items->map->only(['category', 'description', 'amount_usd_cents', 'is_provision'])->all(),
                'issued_at' => now(), 'issued_by' => $actorId,
            ]);
            foreach ($items->where('is_provision', true)->where('amount_usd_cents', '<', 0) as $item) {
                FundUse::query()->create([
                    'financial_period_id' => $period->id, 'period_item_id' => $item->id,
                    'type' => 'provision', 'amount_usd_cents' => -$item->amount_usd_cents,
                    'used_on' => now()->toDateString(), 'actor_id' => $actorId,
                ]);
            }
            foreach ($properties as $property) {
                $property->charges()->create([
                    'financial_period_id' => $period->id, 'kind' => 'period',
                    'description' => 'Cuota de condominio '.$period->month,
                    'charged_on' => $chargedOn, 'due_on' => $dueOn,
                    'principal_usd_cents' => $unitPrincipal, 'reserve_usd_cents' => $unitReserve,
                    'provision_usd_cents' => $unitProvision, 'created_by' => $actorId,
                ]);
                $this->applyCredits($property);
            }

            return $period->load('items', 'charges');
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function recordPayment(array $data, int $actorId): Payment
    {
        $data = Validator::make($data, $this->moneyRules() + [
            'reference' => ['required', 'string', 'max:255'],
            'splits' => ['required', 'array', 'min:1', 'max:90'],
            'splits.*.property_id' => ['required', 'integer', 'distinct', 'exists:properties,id'],
            'splits.*.amount_usd_cents' => ['required', 'integer', 'between:1,'.self::MAX_AMOUNT],
        ])->validate();
        $amountUsd = Money::bsToUsd((int) $data['amount_bs_cents'], (int) $data['rate_micros']);
        if ($amountUsd <= 0 || array_sum(array_column($data['splits'], 'amount_usd_cents')) !== $amountUsd) {
            $this->fail('splits', 'La distribución entre inmuebles debe coincidir exactamente con el importe convertido a USD.');
        }
        usort($data['splits'], fn (array $left, array $right): int => $left['property_id'] <=> $right['property_id']);
        $hash = $this->requestHash($data);

        return DB::transaction(function () use ($data, $actorId, $amountUsd, $hash): Payment {
            $this->lockFunds();
            $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                $this->checkRetry($existing->request_hash, $hash);

                return $existing->load('credits.property');
            }
            $bank = BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id']);
            $this->checkBankDate($bank, $data['paid_on']);
            if (Payment::query()->where('bank_account_id', $bank->id)->whereDate('paid_on', $data['paid_on'])->where('reference', $data['reference'])->exists()) {
                $this->fail('reference', 'La referencia ya fue registrada para esta cuenta y fecha.');
            }
            $properties = Property::query()->whereIn('id', array_column($data['splits'], 'property_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($properties as $property) {
                if (! $property->active || ($property->opening_date !== null && $data['paid_on'] < $property->opening_date->toDateString())) {
                    $this->fail('paid_on', 'El pago requiere un inmueble activo y una fecha igual o posterior a su apertura.');
                }
            }
            $payment = Payment::query()->create([
                'bank_account_id' => $bank->id, 'paid_on' => $data['paid_on'],
                'amount_bs_cents' => $data['amount_bs_cents'], 'rate_micros' => $data['rate_micros'],
                'amount_usd_cents' => $amountUsd, 'reference' => $data['reference'],
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                'created_by' => $actorId, 'status' => 'posted',
            ]);
            foreach ($data['splits'] as $split) {
                $payment->credits()->create([
                    'property_id' => $split['property_id'], 'amount_usd_cents' => $split['amount_usd_cents'],
                    'credited_on' => $data['paid_on'], 'kind' => 'payment', 'created_by' => $actorId,
                ]);
                $this->applyCredits($properties[$split['property_id']]);
            }

            return $payment->load('credits.property');
        }, 3);
    }

    public function voidPayment(Payment $payment, string $reason, int $actorId): Payment
    {
        $this->validateReason($reason);

        return DB::transaction(function () use ($payment, $reason, $actorId): Payment {
            $this->lockFunds();
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'voided') {
                return $payment;
            }
            $bank = BankAccount::query()->lockForUpdate()->findOrFail($payment->bank_account_id);
            $credits = $payment->credits()->lockForUpdate()->get();
            Allocation::query()->whereIn('property_credit_id', $credits->modelKeys())->whereNull('voided_at')->update(['voided_at' => now()]);
            $payment->credits()->update(['status' => 'voided']);
            $payment->update(['status' => 'voided', 'voided_by' => $actorId, 'voided_at' => now(), 'void_reason' => $reason]);
            foreach (Property::query()->whereIn('id', $credits->pluck('property_id'))->orderBy('id')->lockForUpdate()->get() as $property) {
                $this->applyCredits($property);
            }
            if ($this->bankBalance($bank) < 0 || $this->fundBalance('reserve') < 0 || $this->fundBalance('provision') < 0) {
                $this->fail('payment', 'El dinero de este pago ya respalda egresos o uso de fondos. Corrige primero esos movimientos.');
            }

            return $payment->load('credits.property');
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function recordExpense(array $data, int $actorId): Expense
    {
        $data = Validator::make($data, $this->moneyRules() + [
            'description' => ['required', 'string', 'max:255'],
            'fund' => ['required', Rule::in(['operating', 'reserve', 'provision'])],
            'period_item_id' => ['nullable', 'integer', 'exists:period_items,id'],
        ])->validate();
        $amountUsd = Money::bsToUsd((int) $data['amount_bs_cents'], (int) $data['rate_micros']);
        if ($amountUsd < 1) {
            $this->fail('amount_bs_cents', 'El importe convertido debe ser al menos un centavo de USD.');
        }
        $hash = $this->requestHash($data);

        return DB::transaction(function () use ($data, $actorId, $amountUsd, $hash): Expense {
            $this->lockFunds();
            $existing = Expense::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                $this->checkRetry($existing->request_hash, $hash);

                return $existing;
            }
            $bank = BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id']);
            $this->checkBankDate($bank, $data['paid_on']);
            if ($this->bankBalance($bank, $data['paid_on']) < $data['amount_bs_cents'] || $this->bankBalance($bank) < $data['amount_bs_cents']) {
                $this->fail('amount_bs_cents', 'La cuenta bancaria tiene saldo insuficiente para este egreso.');
            }
            if ($data['fund'] !== 'operating' && min($this->fundBalance($data['fund']), $this->fundBalance($data['fund'], $data['paid_on'])) < $amountUsd) {
                $this->fail('fund', 'El fondo seleccionado tiene saldo insuficiente para este egreso.');
            }
            if ($data['fund'] === 'provision' && ! empty($data['period_item_id']) && FundUse::query()->where('period_item_id', $data['period_item_id'])->exists()) {
                $this->fail('fund', 'La provisión de ese concepto ya fue utilizada al emitir. Registra su salida bancaria como gasto operativo.');
            }

            return Expense::query()->create([
                'bank_account_id' => $bank->id, 'period_item_id' => $data['period_item_id'] ?? null,
                'description' => $data['description'], 'paid_on' => $data['paid_on'],
                'amount_bs_cents' => $data['amount_bs_cents'], 'rate_micros' => $data['rate_micros'],
                'amount_usd_cents' => $amountUsd, 'fund' => $data['fund'],
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                'created_by' => $actorId, 'status' => 'posted',
            ]);
        }, 3);
    }

    public function voidExpense(Expense $expense, string $reason, int $actorId): Expense
    {
        $this->validateReason($reason);

        return DB::transaction(function () use ($expense, $reason, $actorId): Expense {
            $this->lockFunds();
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status !== 'voided') {
                $expense->update(['status' => 'voided', 'voided_by' => $actorId, 'voided_at' => now(), 'void_reason' => $reason]);
            }

            return $expense;
        }, 3);
    }

    /** @return array<string, mixed> */
    public function propertyStatement(Property $property): array
    {
        $charges = $property->charges()->with(['allocations' => fn ($query) => $query->whereNull('voided_at')])->orderBy('due_on')->orderBy('id')->get();
        $credits = $property->credits()->where('status', 'active')->with(['allocations' => fn ($query) => $query->whereNull('voided_at'), 'payment'])->orderBy('credited_on')->orderBy('id')->get();
        $debt = 0;
        $overdue = 0;
        foreach ($charges as $charge) {
            $paid = (int) $charge->allocations->sum('principal_usd_cents') + (int) $charge->allocations->sum('reserve_usd_cents');
            $charge->setAttribute('paid_usd_cents', $paid);
            $remaining = $charge->principal_usd_cents + $charge->reserve_usd_cents - $paid;
            $charge->setAttribute('outstanding_usd_cents', $remaining);
            $debt += $remaining;
            if ($charge->due_on->toDateString() < now()->toDateString()) {
                $overdue += $remaining;
            }
        }
        $available = 0;
        foreach ($credits as $credit) {
            $remaining = $credit->amount_usd_cents - (int) $credit->allocations->sum('principal_usd_cents') - (int) $credit->allocations->sum('reserve_usd_cents');
            $credit->setAttribute('available_usd_cents', $remaining);
            $available += $remaining;
        }

        return ['property' => $property, 'charges' => $charges, 'credits' => $credits,
            'balance_usd_cents' => $debt - $available, 'debt_usd_cents' => $debt,
            'credit_usd_cents' => $available, 'overdue_usd_cents' => $overdue];
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $receivable = 0;
        $credits = 0;
        $overdue = 0;
        foreach (Property::query()->get() as $property) {
            $statement = $this->propertyStatement($property);
            $receivable += $statement['debt_usd_cents'];
            $credits += $statement['credit_usd_cents'];
            $overdue += $statement['overdue_usd_cents'];
        }
        $accounts = BankAccount::query()->get()->map(function (BankAccount $account): BankAccount {
            $account->setAttribute('balance_bs_cents', $this->bankBalance($account));

            return $account;
        });
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return ['receivable_usd_cents' => $receivable, 'credits_usd_cents' => $credits,
            'overdue_usd_cents' => $overdue,
            'reserve_usd_cents' => $this->fundBalance('reserve'),
            'provision_usd_cents' => $this->fundBalance('provision'),
            'bank_bs_cents' => (int) $accounts->sum('balance_bs_cents'),
            'collected_month_bs_cents' => (int) Payment::query()->where('status', 'posted')->whereBetween('paid_on', [$start, $end])->sum('amount_bs_cents'),
            'spent_month_bs_cents' => (int) Expense::query()->where('status', 'posted')->whereBetween('paid_on', [$start, $end])->sum('amount_bs_cents'),
            'accounts' => $accounts];
    }

    public function bankBalance(BankAccount $bank, ?string $asOf = null): int
    {
        $date = $asOf ?? now()->toDateString();
        $opening = $bank->opening_date === null || $bank->opening_date->toDateString() <= $date ? $bank->opening_balance_bs_cents : 0;

        return $opening
            + (int) $bank->payments()->where('status', 'posted')->whereDate('paid_on', '<=', $date)->sum('amount_bs_cents')
            - (int) $bank->expenses()->where('status', 'posted')->whereDate('paid_on', '<=', $date)->sum('amount_bs_cents');
    }

    public function fundBalance(string $type, ?string $asOf = null): int
    {
        if (! in_array($type, ['reserve', 'provision'], true)) {
            $this->fail('fund', 'Tipo de fondo inválido.');
        }
        $date = $asOf ?? now()->toDateString();
        $opening = FundOpening::query()->where('type', $type)->whereDate('date', '<=', $date)->value('amount_usd_cents') ?? 0;

        return (int) $opening
            + (int) Allocation::query()->whereNull('voided_at')->whereDate('applied_on', '<=', $date)->sum($type.'_usd_cents')
            - (int) Expense::query()->where('status', 'posted')->where('fund', $type)->whereDate('paid_on', '<=', $date)->sum('amount_usd_cents')
            - (int) FundUse::query()->where('type', $type)->whereDate('used_on', '<=', $date)->sum('amount_usd_cents');
    }

    private function applyCredits(Property $property): void
    {
        $charges = $property->charges()->orderBy('due_on')->orderBy('charged_on')->orderBy('id')->lockForUpdate()->get();
        $credits = $property->credits()->where('status', 'active')->orderBy('credited_on')->orderBy('id')->lockForUpdate()->get();
        foreach ($credits as $credit) {
            $applied = $credit->allocations()->whereNull('voided_at');
            $available = $credit->amount_usd_cents - (int) (clone $applied)->sum('principal_usd_cents') - (int) (clone $applied)->sum('reserve_usd_cents');
            foreach ($charges as $charge) {
                if ($available <= 0) {
                    break;
                }
                $paid = $charge->allocations()->whereNull('voided_at')->get();
                $principal = $charge->principal_usd_cents - (int) $paid->sum('principal_usd_cents');
                $reserve = $charge->reserve_usd_cents - (int) $paid->sum('reserve_usd_cents');
                $provision = $charge->provision_usd_cents - (int) $paid->sum('provision_usd_cents');
                $remaining = $principal + $reserve;
                if ($remaining <= 0) {
                    continue;
                }
                $amount = min($available, $remaining);
                $allocatedReserve = Money::ratio($reserve, $amount, $remaining);
                $allocatedPrincipal = $amount - $allocatedReserve;
                $allocatedProvision = $principal > 0 ? Money::ratio($provision, $allocatedPrincipal, $principal) : 0;
                Allocation::query()->create([
                    'property_credit_id' => $credit->id, 'charge_id' => $charge->id,
                    'principal_usd_cents' => $allocatedPrincipal, 'reserve_usd_cents' => $allocatedReserve,
                    'provision_usd_cents' => $allocatedProvision,
                    'applied_on' => max($credit->credited_on->toDateString(), $charge->charged_on->toDateString()),
                ]);
                $available -= $amount;
            }
        }
    }

    /** @return array<string, array<mixed>> */
    private function moneyRules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount_bs_cents' => ['required', 'integer', 'between:1,'.self::MAX_AMOUNT],
            'rate_micros' => ['required', 'integer', 'between:1,'.self::MAX_AMOUNT],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    private function checkBankDate(BankAccount $bank, string $date): void
    {
        if (! $bank->active || ($bank->opening_date !== null && $date < $bank->opening_date->toDateString())) {
            $this->fail('bank_account_id', 'Selecciona una cuenta activa y una fecha igual o posterior a su apertura.');
        }
    }

    private function validateReason(string $reason): void
    {
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'min:3', 'max:2000']])->validate();
    }

    /** @param array<string, mixed> $data */
    private function requestHash(array $data): string
    {
        unset($data['idempotency_key']);
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function checkRetry(string $existingHash, string $hash): void
    {
        if (! hash_equals($existingHash, $hash)) {
            $this->fail('idempotency_key', 'Esta operación ya fue recibida con datos diferentes.');
        }
    }

    /** Serializes financial writes across accounts and periods to preserve allocations and fund availability. */
    private function lockFunds(): void
    {
        FundOpening::query()->orderBy('id')->lockForUpdate()->get();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
