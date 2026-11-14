<?php

namespace App\Jobs;

use App\Http\Controllers\DocumentController;
use App\Models\Charge;
use App\Models\Delivery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCondominiumEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $deliveryId) {}

    /**
     * Ejecuta el trabajo de envío de correo.
     */
    public function handle(): void
    {
        $claimed = Delivery::whereKey($this->deliveryId)->whereIn('status', ['queued', 'failed'])->update(['status' => 'sending']);
        if (! $claimed) {
            return;
        }
        $delivery = Delivery::findOrFail($this->deliveryId);
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $delivery->update(['status' => 'preview']);

            return;
        }
        try {
            $pdf = null;
            if ($delivery->charge_id) {
                $pdf = Pdf::loadView('documents.charge', DocumentController::chargeData(Charge::findOrFail($delivery->charge_id)))->setPaper('a4')->output();
            }
            Mail::send('emails.delivery', ['delivery' => $delivery], function ($message) use ($delivery, $pdf): void {
                $message->to($delivery->recipient)->subject($delivery->subject);
                if ($pdf !== null) {
                    $message->attachData($pdf, 'aviso-'.$delivery->charge_id.'.pdf', ['mime' => 'application/pdf']);
                }
            });
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $exception) {
            $delivery->update(['status' => 'failed', 'error' => 'El proveedor no confirmó el envío. Revisa la configuración y el registro del servidor.']);
            throw $exception;
        }
    }
}
