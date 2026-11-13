<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\BankAccount;
use App\Models\Charge;
use App\Models\FinancialPeriod;
use App\Models\FundUse;
use App\Models\Payment;
use App\Models\PeriodItem;
use App\Models\Property;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_initial_debt_and_credit_are_separate_from_bank_cash(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        $debt = Property::factory()->create();
        $credit = Property::factory()->create();
        $bank = BankAccount::factory()->create(['opening_balance_bs_cents' => 400000]);
        $finance = app(FinanceService::class);

        $finance->setOpeningBalance($debt, 5000, '2026-01-01', $user->id);
        $finance->setOpeningBalance($credit, -2000, '2026-01-01', $user->id);

        $this->assertSame(5000, $finance->propertyStatement($debt)['debt_usd_cents']);
        $this->assertSame(2000, $finance->propertyStatement($credit)['credit_usd_cents']);
        $this->assertSame(400000, $finance->bankBalance($bank));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_issues_equal_ninety_unit_charges_once_with_explicit_rounding_and_a_snapshot(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        Property::factory()->count(90)->create();
        $period = FinancialPeriod::factory()->create();
        PeriodItem::factory()->for($period, 'period')->create(['amount_usd_cents' => 100001]);
        $finance = app(FinanceService::class);

        $issued = $finance->issuePeriod($period, $user->id);
        $finance->issuePeriod($period, $user->id);

        $this->assertSame(100001, $issued->net_usd_cents);
        $this->assertSame(10000, $issued->reserve_usd_cents);
        $this->assertSame(1222, $issued->unit_charge_usd_cents);
        $this->assertSame(-21, $issued->rounding_adjustment_usd_cents);
        $this->assertSame(100001, $issued->items_snapshot[0]['amount_usd_cents']);
        $this->assertDatabaseCount('charges', 90);
        $this->assertSame(109980, (int) Charge::query()->sum('principal_usd_cents') + (int) Charge::query()->sum('reserve_usd_cents'));
        $this->assertSame(0, $finance->dashboard()['bank_bs_cents']);
    }

    public function test_applies_a_multi_property_transfer_to_oldest_debt_and_keeps_an_advance(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        $properties = Property::factory()->count(2)->create();
        $bank = BankAccount::factory()->create();
        $finance = app(FinanceService::class);
        $finance->setOpeningBalance($properties[0], 2000, '2026-01-01', $user->id);
        $charge = Charge::factory()->for($properties[0])->create([
            'kind' => 'period', 'charged_on' => '2026-11-01', 'due_on' => '2026-11-30',
            'principal_usd_cents' => 1000, 'reserve_usd_cents' => 100, 'provision_usd_cents' => 200,
        ]);
        $data = $this->paymentData($bank, [['property_id' => $properties[0]->id, 'amount_usd_cents' => 2550], ['property_id' => $properties[1]->id, 'amount_usd_cents' => 1000]], 355000);

        $payment = $finance->recordPayment($data, $user->id);
        $retry = $finance->recordPayment($data, $user->id);

        $this->assertSame($payment->id, $retry->id);
        $this->assertSame(550, $finance->propertyStatement($properties[0])['debt_usd_cents']);
        $this->assertSame(1000, $finance->propertyStatement($properties[1])['credit_usd_cents']);
        $this->assertSame(50, $finance->fundBalance('reserve'));
        $this->assertSame(100, $finance->fundBalance('provision'));
        $this->assertSame(355000, $finance->bankBalance($bank));
        $this->assertSame(500, (int) $charge->allocations()->sum('principal_usd_cents'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('property_credits', 2);
    }

    public function test_advance_is_applied_once_to_future_period_and_void_restores_the_debt_and_funds(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        $properties = Property::factory()->count(90)->create();
        $bank = BankAccount::factory()->create();
        $finance = app(FinanceService::class);
        $payment = $finance->recordPayment($this->paymentData($bank, [['property_id' => $properties[0]->id, 'amount_usd_cents' => 1500]], 150000), $user->id);
        $period = FinancialPeriod::factory()->create();
        PeriodItem::factory()->for($period, 'period')->create(['amount_usd_cents' => 90000]);

        $finance->issuePeriod($period, $user->id);
        $finance->issuePeriod($period, $user->id);

        $this->assertSame(400, $finance->propertyStatement($properties[0])['credit_usd_cents']);
        $this->assertSame(100, $finance->fundBalance('reserve'));
        $finance->voidPayment($payment, 'Transferencia registrada por error', $user->id);
        $finance->voidPayment($payment, 'Transferencia registrada por error', $user->id);
        $this->assertSame(1100, $finance->propertyStatement($properties[0])['debt_usd_cents']);
        $this->assertSame(0, $finance->propertyStatement($properties[0])['credit_usd_cents']);
        $this->assertSame(0, $finance->fundBalance('reserve'));
        $this->assertSame(0, $finance->bankBalance($bank));
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'voided', 'voided_by' => $user->id]);
        $this->assertSame(1, Allocation::query()->whereNotNull('voided_at')->count());
    }

    public function test_void_reapplies_other_available_credit_instead_of_leaving_artificial_debt(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        $property = Property::factory()->create();
        $bank = BankAccount::factory()->create();
        $finance = app(FinanceService::class);
        $finance->setOpeningBalance($property, 1000, '2026-01-01', $user->id);
        $first = $finance->recordPayment($this->paymentData($bank, [['property_id' => $property->id, 'amount_usd_cents' => 1000]], 100000), $user->id);
        $finance->recordPayment($this->paymentData($bank, [['property_id' => $property->id, 'amount_usd_cents' => 1000]], 100000, 'second'), $user->id);

        $finance->voidPayment($first, 'Duplicado de comprobante', $user->id);

        $this->assertSame(0, $finance->propertyStatement($property)['balance_usd_cents']);
        $this->assertSame(0, $finance->propertyStatement($property)['credit_usd_cents']);
        $this->assertSame(100000, $finance->bankBalance($bank));
    }

    public function test_negative_provision_line_uses_the_fund_without_moving_bank_cash(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        Property::factory()->count(90)->create();
        $bank = BankAccount::factory()->create(['opening_balance_bs_cents' => 1000000]);
        $period = FinancialPeriod::factory()->create();
        PeriodItem::factory()->for($period, 'period')->create(['amount_usd_cents' => 90000]);
        PeriodItem::factory()->for($period, 'period')->create(['amount_usd_cents' => -9000, 'is_provision' => true]);
        $finance = app(FinanceService::class);
        $finance->setFundOpening('provision', 10000, '2026-01-01', $user->id);

        $issued = $finance->issuePeriod($period, $user->id);

        $this->assertSame(81000, $issued->net_usd_cents);
        $this->assertSame(1000, $finance->fundBalance('provision'));
        $this->assertSame(1000000, $finance->bankBalance($bank));
        $this->assertSame(9000, (int) FundUse::query()->sum('amount_usd_cents'));
    }

    public function test_paying_an_actual_expense_changes_bank_and_reserve_once_and_can_be_voided(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        $bank = BankAccount::factory()->create(['opening_balance_bs_cents' => 500000]);
        $finance = app(FinanceService::class);
        $finance->setFundOpening('reserve', 5000, '2026-01-01', $user->id);
        $data = ['bank_account_id' => $bank->id, 'paid_on' => '2026-11-10', 'amount_bs_cents' => 200000,
            'rate_micros' => 100000000, 'description' => 'Reparación de portón', 'fund' => 'reserve', 'idempotency_key' => 'expense-1'];

        $expense = $finance->recordExpense($data, $user->id);
        $finance->recordExpense($data, $user->id);

        $this->assertSame(300000, $finance->bankBalance($bank));
        $this->assertSame(3000, $finance->fundBalance('reserve'));
        $this->assertDatabaseCount('expenses', 1);
        $finance->voidExpense($expense, 'Comprobante incorrecto', $user->id);
        $this->assertSame(500000, $finance->bankBalance($bank));
        $this->assertSame(5000, $finance->fundBalance('reserve'));
    }

    public function test_rejects_a_split_mismatch_without_recording_cash(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        $property = Property::factory()->create();
        $bank = BankAccount::factory()->create();
        try {
            app(FinanceService::class)->recordPayment($this->paymentData($bank, [['property_id' => $property->id, 'amount_usd_cents' => 999]], 100000), $user->id);
            $this->fail('La distribución incorrecta fue aceptada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('splits', $exception->errors());
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('property_credits', 0);
    }

    public function test_rejects_mutating_an_issued_period_item(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        Property::factory()->count(90)->create();
        $period = FinancialPeriod::factory()->create();
        $item = PeriodItem::factory()->for($period, 'period')->create();
        app(FinanceService::class)->issuePeriod($period, $user->id);

        $this->expectException(ValidationException::class);
        $item->update(['amount_usd_cents' => 1]);
    }

    public function test_rejects_insufficient_provisions_and_rolls_back_period_issuance(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12));
        $user = User::factory()->create();
        Property::factory()->count(90)->create();
        $period = FinancialPeriod::factory()->create();
        PeriodItem::factory()->for($period, 'period')->create();
        PeriodItem::factory()->for($period, 'period')->create(['amount_usd_cents' => -1000, 'is_provision' => true]);
        try {
            app(FinanceService::class)->issuePeriod($period, $user->id);
            $this->fail('Se permitió usar un fondo sin saldo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame('draft', $period->refresh()->status);
        $this->assertDatabaseCount('charges', 0);
        $this->assertDatabaseCount('fund_uses', 0);
    }

    /** @param array<int, array{property_id: int, amount_usd_cents: int}> $splits */
    private function paymentData(BankAccount $bank, array $splits, int $bsCents, string $key = 'payment-1'): array
    {
        return ['bank_account_id' => $bank->id, 'paid_on' => '2026-11-10', 'amount_bs_cents' => $bsCents,
            'rate_micros' => 100000000, 'reference' => $key, 'idempotency_key' => $key, 'splits' => $splits];
    }
}
