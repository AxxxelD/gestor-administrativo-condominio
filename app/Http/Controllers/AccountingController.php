<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseTemplate;
use App\Models\FinancialPeriod;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\PeriodItem;
use App\Models\Person;
use App\Models\Setting;
use App\Services\ExchangeRateService;
use App\Services\FinanceService;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingController extends Controller
{
    private const CATEGORIES = ['sector_fixed', 'sector_variable', 'common_fixed', 'common_variable'];

    public function period(Request $request): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        $data = $request->validate(['month' => ['required', 'date_format:Y-m', 'unique:financial_periods,month'], 'reserve_basis_points' => ['required', 'integer', 'between:0,10000']]);
        $period = FinancialPeriod::create($data + ['status' => 'draft']);
        AuditLog::record('create', $period, $data);

        return response()->json($period, 201);
    }

    public function recurring(Request $request, FinancialPeriod $period): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        DB::transaction(function () use ($period): void {
            $this->draft($period);
            foreach (ExpenseTemplate::where('active', true)->where('recurring', true)->get() as $template) {
                PeriodItem::firstOrCreate(['financial_period_id' => $period->id, 'expense_template_id' => $template->id], $template->only(['description', 'category', 'amount_usd_cents', 'is_provision']));
            }
            AuditLog::record('load_recurring', $period);
        });

        return response()->json(['message' => 'Conceptos habituales cargados.']);
    }

    public function item(Request $request, FinancialPeriod $period): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        $data = $this->itemData($request);
        $item = DB::transaction(function () use ($period, $data): PeriodItem {
            $this->draft($period);
            $item = PeriodItem::create($data + ['financial_period_id' => $period->id]);
            AuditLog::record('create', $item, $data);

            return $item;
        });

        return response()->json($item, 201);
    }

    public function updateItem(Request $request, PeriodItem $item): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        $data = $this->itemData($request);
        DB::transaction(function () use ($item, $data): void {
            $this->draft(FinancialPeriod::findOrFail($item->financial_period_id));
            $item->update($data);
            AuditLog::record('update', $item, $data);
        });

        return response()->json($item);
    }

    public function deleteItem(Request $request, PeriodItem $item): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        DB::transaction(function () use ($item): void {
            $this->draft(FinancialPeriod::findOrFail($item->financial_period_id));
            AuditLog::record('delete', $item, $item->only(['description', 'amount_usd_cents']));
            $item->delete();
        });

        return response()->json(['message' => 'Concepto retirado del borrador.']);
    }

    public function attachment(Request $request, PeriodItem $item): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        $request->validate(['attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']]);
        $file = $request->file('attachment');
        $path = $file->store('receipts', 'local');
        try {
            DB::transaction(function () use ($item, $path, $file): void {
                $this->draft(FinancialPeriod::findOrFail($item->financial_period_id));
                $item->update(['attachment_path' => $path, 'attachment_name' => $file->getClientOriginalName()]);
                AuditLog::record('attach', $item);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json($item);
    }

    public function downloadAttachment(Request $request, PeriodItem $item): BinaryFileResponse
    {
        $this->permit($request, 'finance.manage');
        abort_unless($item->attachment_path && Storage::disk('local')->exists($item->attachment_path), 404);

        return response()->download(Storage::disk('local')->path($item->attachment_path), $item->attachment_name);
    }

    public function issue(Request $request, FinancialPeriod $period, FinanceService $finance): JsonResponse
    {
        $this->permit($request, 'finance.manage');

        return response()->json(DB::transaction(function () use ($finance, $period, $request): FinancialPeriod {
            $result = $finance->issuePeriod($period, $request->user()->id);
            AuditLog::record('issue', $result);
            Setting::firstOrCreate(['key' => 'period_document_'.$period->id], ['value' => Setting::values()]);
            foreach ($result->charges()->with('property')->get() as $charge) {
                if ($charge->document_snapshot !== null) {
                    continue;
                }
                $owners = Occupancy::where('property_id', $charge->property_id)->where('relationship', 'owner')->whereDate('starts_on', '<=', today())->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->pluck('person_id');
                $state = $finance->propertyStatement($charge->property);
                $charge->forceFill(['document_snapshot' => [
                    'code' => $charge->property->code,
                    'owners' => Person::whereIn('id', $owners)->pluck('name')->join(', '),
                    'balance_usd_cents' => $state['balance_usd_cents'],
                    'settings' => Setting::values(),
                ]])->save();
            }

            return $result;
        }));
    }

    public function template(Request $request, ?ExpenseTemplate $template = null): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        $data = $this->itemData($request);
        unset($data['service_date']);
        $data += $request->validate(['recurring' => ['required', 'boolean'], 'active' => ['required', 'boolean']]);
        $template ??= new ExpenseTemplate;
        $template->fill($data)->save();
        AuditLog::record('save', $template, $data);

        return response()->json($template);
    }

    public function bank(Request $request): JsonResponse
    {
        $this->permit($request, 'treasury.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'opening_balance_bs' => ['required', 'numeric', 'between:0,1000000000', 'regex:/^\d+(\.\d{1,2})?$/']]);
        $bank = BankAccount::create(['name' => $data['name'], 'opening_balance_bs_cents' => Money::parse((string) $data['opening_balance_bs']), 'active' => true]);
        AuditLog::record('create', $bank);

        return response()->json($bank, 201);
    }

    public function payment(Request $request, FinanceService $finance): JsonResponse
    {
        $this->permit($request, 'payments.manage');
        $data = $this->transferData($request);
        $splitData = $request->validate(['reference' => ['required', 'string', 'max:100'], 'splits' => ['required', 'array', 'min:1'], 'splits.*.property_id' => ['required', 'integer', 'distinct', 'exists:properties,id'], 'splits.*.amount_usd' => ['required', 'numeric', 'gt:0', 'max:10000000', 'regex:/^\d+(\.\d{1,2})?$/']]);
        $data['reference'] = $splitData['reference'];
        $data['splits'] = [];
        foreach ($splitData['splits'] as $split) {
            abort_unless($request->user()->canAccessProperty((int) $split['property_id']), 403);
            $data['splits'][] = ['property_id' => (int) $split['property_id'], 'amount_usd_cents' => Money::parse((string) $split['amount_usd'])];
        }
        $payment = DB::transaction(function () use ($finance, $data, $request): Payment {
            $payment = $finance->recordPayment($data, $request->user()->id);
            AuditLog::record('payment', $payment, ['rate_date' => $request->input('paid_on'), 'rate_source' => $request->input('rate_source', 'Registro autorizado')]);

            return $payment;
        });

        return response()->json($payment, 201);
    }

    public function voidPayment(Request $request, Payment $payment, FinanceService $finance): JsonResponse
    {
        $this->permit($request, 'payments.manage');
        $this->permit($request, 'finance.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $result = $finance->voidPayment($payment, $data['reason'], $request->user()->id);
        AuditLog::record('void', $result, $data);

        return response()->json($result);
    }

    public function expense(Request $request, FinanceService $finance): JsonResponse
    {
        $this->permit($request, 'treasury.manage');
        $data = $this->transferData($request) + $request->validate(['description' => ['required', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:100'], 'fund' => ['required', Rule::in(['operating', 'reserve', 'provision'])], 'period_item_id' => ['nullable', 'exists:period_items,id']]);
        $expense = $finance->recordExpense($data, $request->user()->id);
        AuditLog::record('expense', $expense);

        return response()->json($expense, 201);
    }

    public function voidExpense(Request $request, Expense $expense, FinanceService $finance): JsonResponse
    {
        $this->permit($request, 'treasury.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $result = $finance->voidExpense($expense, $data['reason'], $request->user()->id);
        AuditLog::record('void', $result, $data);

        return response()->json($result);
    }

    public function fundOpening(Request $request, FinanceService $finance): JsonResponse
    {
        $this->permit($request, 'treasury.manage');
        $data = $request->validate(['fund' => ['required', Rule::in(['reserve', 'provision'])], 'amount_usd' => ['required', 'numeric', 'between:0,10000000', 'regex:/^\d+(\.\d{1,2})?$/'], 'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $opening = $finance->setFundOpening($data['fund'], Money::parse((string) $data['amount_usd']), $data['date'], $request->user()->id);
        AuditLog::record('opening', $opening);

        return response()->json($opening);
    }

    public function rate(Request $request, string $date, ExchangeRateService $rates): JsonResponse
    {
        $this->permit($request, 'finance.view');
        validator(['date' => $date], ['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->validate();

        return response()->json($rates->lookup($date));
    }

    public function saveRate(Request $request): JsonResponse
    {
        $this->permit($request, 'finance.manage');
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'rate' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,6})?$/'], 'reason' => ['required', 'string', 'min:5', 'max:255']]);
        $previous = ExchangeRate::where('date', $data['date'])->first()?->toArray();
        $rate = ExchangeRate::updateOrCreate(['date' => $data['date']], ['rate_micros' => Money::rate((string) $data['rate']), 'reference_date' => $data['date'], 'source' => 'Manual', 'reason' => $data['reason'], 'created_by' => $request->user()->id]);
        AuditLog::record('rate', $rate, ['previous' => $previous, 'reason' => $data['reason']]);

        return response()->json($rate);
    }

    public function settings(Request $request): JsonResponse
    {
        $this->permit($request, 'settings.manage');
        $data = $request->validate(['condominium_name' => ['required', 'string', 'max:150'], 'rif' => ['nullable', 'string', 'max:30'], 'contact_email' => ['nullable', 'email'], 'bank_instructions' => ['nullable', 'string', 'max:1500'], 'notes' => ['nullable', 'string', 'max:4000']]);
        foreach ($data as $key => $value) {
            $setting = Setting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
            AuditLog::record('settings', $setting);
        }

        return response()->json(Setting::values());
    }

    private function permit(Request $request, string $permission): void
    {
        abort_unless($request->user()->allows($permission), 403, 'Tu cuenta no tiene permiso para esta operación.');
    }

    private function draft(FinancialPeriod $period): void
    {
        $locked = FinancialPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== 'draft') {
            throw ValidationException::withMessages(['period' => 'El período ya fue emitido y conserva sus importes históricos.']);
        }
    }

    private function itemData(Request $request): array
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:255'], 'category' => ['required', Rule::in(self::CATEGORIES)], 'amount_usd' => ['required', 'numeric', 'between:-10000000,10000000', 'regex:/^-?\d+(\.\d{1,2})?$/'], 'is_provision' => ['required', 'boolean'], 'service_date' => ['nullable', 'date_format:Y-m-d']]);
        $data['amount_usd_cents'] = Money::parse((string) $data['amount_usd']);
        unset($data['amount_usd']);

        return $data;
    }

    private function transferData(Request $request): array
    {
        $data = $request->validate(['bank_account_id' => ['required', 'exists:bank_accounts,id'], 'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'amount_bs' => ['required', 'numeric', 'gt:0', 'max:1000000000', 'regex:/^\d+(\.\d{1,2})?$/'], 'rate' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,6})?$/'], 'idempotency_key' => ['required', 'uuid']]);
        $data['amount_bs_cents'] = Money::parse((string) $data['amount_bs']);
        $data['rate_micros'] = Money::rate((string) $data['rate']);
        $saved = ExchangeRate::where('date', $data['paid_on'])->first();
        if ((! $saved || $saved->rate_micros !== $data['rate_micros']) && ! $request->user()->allows('finance.manage')) {
            throw ValidationException::withMessages(['rate' => 'Selecciona la tasa registrada para esa fecha o solicita que la administradora la registre.']);
        }
        $usesSaved = $saved && $saved->rate_micros === $data['rate_micros'];
        $data['rate_source'] = $usesSaved ? $saved->source : 'Manual al registrar';
        $data['rate_reference_date'] = $usesSaved ? $saved->reference_date : $data['paid_on'];
        unset($data['amount_bs'], $data['rate']);

        return $data;
    }
}
