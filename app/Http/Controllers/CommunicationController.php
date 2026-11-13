<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\{Delivery, Occupancy, Person, Charge, Property, AuditLog};
use App\Jobs\SendCondominiumEmail;

class CommunicationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->allows('communications.manage'), 403);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'kind' => ['required', Rule::in(['announcement', 'notice'])],
            'property_ids' => ['present', 'array'],
            'property_ids.*' => ['integer', 'exists:properties,id'],
            'period_id' => ['required_if:kind,notice', 'nullable', 'exists:financial_periods,id'],
        ]);
        $ids = $data['property_ids'];
        if ($ids === []) {
            $ids = $request->user()->hasGlobalPropertyAccess() ? Property::where('active', true)->pluck('id')->all() : $request->user()->property_ids;
        }
        foreach ($ids as $id) {
            abort_unless($request->user()->canAccessProperty((int) $id), 403);
        }
        $deliveries = DB::transaction(function () use ($data, $ids, $request): array {
            $created = [];
            $seen = [];
            foreach ($ids as $id) {
                $charge = null;
                if ($data['kind'] === 'notice') {
                    $charge = Charge::where('property_id', $id)->where('financial_period_id', $data['period_id'])->first();
                    if (! $charge) {
                        continue;
                    }
                }
                $occupants = Occupancy::where('property_id', $id)->whereIn('relationship', ['owner', 'tenant'])->whereDate('starts_on', '<=', today())->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->pluck('person_id');
                foreach (Person::whereIn('id', $occupants)->whereNotNull('email')->get() as $person) {
                    $key = strtolower($person->email).'|'.($charge?->id ?? 'general');
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $delivery = Delivery::create(['recipient' => $person->email, 'subject' => $data['subject'], 'body' => $data['body'], 'kind' => $data['kind'], 'charge_id' => $charge?->id, 'status' => $this->isPreview() ? 'preview' : 'queued', 'created_by' => $request->user()->id]);
                    AuditLog::record('email_prepare', $delivery);
                    if (! $this->isPreview()) {
                        SendCondominiumEmail::dispatch($delivery->id)->afterCommit();
                    }
                    $created[] = $delivery;
                }
            }
            return $created;
        });
        return response()->json(['deliveries' => $deliveries, 'message' => count($deliveries).' correos preparados'.($this->isPreview() ? ' en vista previa. Configura el servicio de correo para entregarlos.' : ' para envío.')]);
    }

    public function preview(Request $request, Delivery $delivery): View
    {
        abort_unless($request->user()->allows('communications.manage') && ($request->user()->hasGlobalPropertyAccess() || $delivery->created_by === $request->user()->id), 403);
        return view('emails.delivery', ['delivery' => $delivery]);
    }

    public function retry(Request $request, Delivery $delivery): JsonResponse
    {
        abort_unless($request->user()->allows('communications.manage') && ($request->user()->hasGlobalPropertyAccess() || $delivery->created_by === $request->user()->id), 403);
        abort_if($this->isPreview(), 422, 'Configura el servicio de correo antes de enviar.');
        DB::transaction(function () use ($delivery): void {
            $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['preview', 'failed'], true), 422, 'El correo ya fue enviado o está en proceso.');
            $locked->update(['status' => 'queued', 'error' => null]);
            SendCondominiumEmail::dispatch($locked->id)->afterCommit();
        });
        return response()->json(['message' => 'Correo puesto en cola.']);
    }

    private function isPreview(): bool
    {
        return in_array(config('mail.default'), ['log', 'array'], true);
    }
}
