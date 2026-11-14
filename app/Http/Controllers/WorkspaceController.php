<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Charge;
use App\Models\Delivery;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseTemplate;
use App\Models\FinancialPeriod;
use App\Models\FundOpening;
use App\Models\Occupancy;
use App\Models\Payment;
use App\Models\PeriodItem;
use App\Models\Person;
use App\Models\Property;
use App\Models\Setting;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __invoke(Request $request, FinanceService $finance): JsonResponse
    {
        $user = $request->user();
        $global = $user->hasGlobalPropertyAccess();
        $properties = Property::query()->when(! $global, fn ($query) => $query->whereIn('id', $user->property_ids ?? []))->orderBy('code')->get();
        $ids = $properties->modelKeys();
        $occupancies = Occupancy::whereIn('property_id', $ids)->get();
        $people = Person::query()->when(! $user->allows('community.manage'), fn ($query) => $query->whereIn('id', $occupancies->pluck('person_id')))->orderBy('name')->get();
        $dashboard = ['receivable_usd_cents' => 0, 'credits_usd_cents' => 0, 'reserve_usd_cents' => 0, 'provision_usd_cents' => 0, 'bank_bs_cents' => 0, 'collected_month_bs_cents' => 0, 'spent_month_bs_cents' => 0, 'accounts' => []];
        if ($user->allows('treasury.view')) {
            $dashboard = $finance->dashboard();
        }
        $dashboard['receivable_usd_cents'] = 0;
        $dashboard['credits_usd_cents'] = 0;
        foreach ($properties as $property) {
            if ($user->allows('finance.view')) {
                $statement = $finance->propertyStatement($property);
                $property->setAttribute('balance_usd_cents', $statement['balance_usd_cents']);
                $dashboard['receivable_usd_cents'] += $statement['debt_usd_cents'];
                $dashboard['credits_usd_cents'] += $statement['credit_usd_cents'];
            }
        }
        $canViewCommunity = $user->allows('community.view');
        $canViewFinance = $user->allows('finance.view');
        $canViewReport = $user->allows('reports.view');
        $periods = FinancialPeriod::query()->when(! $user->allows('finance.manage'), fn ($query) => $query->where('status', 'issued'))->orderByDesc('month')->get();
        $items = PeriodItem::whereIn('financial_period_id', $periods->modelKeys())->get()->makeHidden(['attachment_path']);

        return response()->json([
            'user' => $user,
            'dashboard' => $dashboard,
            'properties' => $properties,
            'people' => $canViewCommunity ? $people : [],
            'occupancies' => $canViewCommunity ? $occupancies : [],
            'periods' => $canViewFinance || $canViewReport ? $periods : [],
            'items' => $canViewFinance || $canViewReport ? $items : [],
            'templates' => $user->allows('finance.manage') ? ExpenseTemplate::orderBy('description')->get() : [],
            'banks' => $user->allows('treasury.view') || $user->allows('payments.manage') ? BankAccount::all() : [],
            'payments' => $user->allows('payments.view') && $global ? Payment::latest('paid_on')->get() : [],
            'expenses' => $user->allows('treasury.view') ? Expense::latest('paid_on')->get() : [],
            'charges' => $canViewFinance ? Charge::whereIn('property_id', $ids)->orderByDesc('charged_on')->get() : [],
            'rates' => ExchangeRate::orderByDesc('date')->limit(90)->get(),
            'settings' => Setting::values(),
            'deliveries' => $user->allows('communications.manage') ? Delivery::query()->when(! $global, fn ($query) => $query->where('created_by', $user->id))->latest()->limit(200)->get() : [],
            'fund_openings' => $user->allows('treasury.view') ? FundOpening::all() : [],
            'audit' => $user->allows('settings.manage') ? AuditLog::latest()->limit(50)->get() : [],
            'mail_mode' => config('mail.default'),
        ]);
    }
}
