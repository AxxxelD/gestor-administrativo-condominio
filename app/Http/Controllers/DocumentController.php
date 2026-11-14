<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Setting;
use App\Services\FinanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function charge(Request $request, Charge $charge): Response
    {
        abort_unless($request->user()->allows('finance.view') && $request->user()->canAccessProperty($charge->property_id), 403);

        return Pdf::loadView('documents.charge', self::chargeData($charge))->setPaper('a4')->download('aviso-'.$charge->id.'.pdf');
    }

    public static function chargeData(Charge $charge): array
    {
        $charge->load(['property', 'period']);
        $snapshot = $charge->document_snapshot ?? [];

        return [
            'charge' => $charge,
            'period' => $charge->period,
            'items' => $charge->period?->items_snapshot ?? [],
            'settings' => $snapshot['settings'] ?? Setting::values(),
            'code' => $snapshot['code'] ?? $charge->property->code,
            'owners' => $snapshot['owners'] ?? '',
            'balance' => $snapshot['balance_usd_cents'] ?? null,
        ];
    }

    public function payment(Request $request, Payment $payment): Response
    {
        abort_unless($request->user()->allows('payments.view'), 403);
        $payment->load('credits.property');
        foreach ($payment->credits as $credit) {
            abort_unless($request->user()->canAccessProperty($credit->property_id), 403);
        }

        return Pdf::loadView('documents.payment', ['payment' => $payment, 'settings' => Setting::values()])->setPaper('a4')->download('pago-'.$payment->id.'.pdf');
    }

    public function statement(Request $request, Property $property, FinanceService $finance): Response
    {
        abort_unless($request->user()->allows('finance.view') && $request->user()->canAccessProperty($property->id), 403);
        $state = $finance->propertyStatement($property);

        return Pdf::loadView('documents.statement', ['state' => $state, 'property' => $property, 'settings' => Setting::values()])->setPaper('a4')->download('estado-'.$property->code.'.pdf');
    }
}
