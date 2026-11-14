<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class ExchangeRateService
{
    public function lookup(string $date): ExchangeRate
    {
        $saved = ExchangeRate::where('date', $date)->first();
        if ($saved) {
            return $saved;
        }

        $requested = CarbonImmutable::parse($date, 'America/Caracas')->startOfDay();
        $today = CarbonImmutable::today('America/Caracas');
        if ($requested->greaterThan($today)) {
            throw ValidationException::withMessages(['date' => 'La fecha de la tasa no puede ser futura.']);
        }

        try {
            $path = $requested->equalTo($today)
                ? 'dolares/oficial'
                : 'historicos/dolares/oficial/'.$requested->format('Y/m/d');
            $response = Http::acceptJson()->connectTimeout(3)->timeout(10)->get('https://ve.dolarapi.com/v1/'.$path);
            if ($response->successful()) {
                $data = $response->json();
                $reference = $data['fecha'] ?? $data['fechaActualizacion'] ?? null;
                if ($reference && isset($data['promedio'])) {
                    $referenceDate = CarbonImmutable::parse($reference)->setTimezone('America/Caracas')->toDateString();
                    if ($referenceDate === $date) {
                        return $this->save($date, (string) $data['promedio'], 'DolarApi · BCV', $referenceDate);
                    }
                }
            }

            if ($requested->isWeekend()) {
                for ($days = 1; $days <= 5; $days++) {
                    $previous = $requested->subDays($days);
                    $response = Http::acceptJson()->connectTimeout(2)->timeout(3)->get('https://ve.dolarapi.com/v1/historicos/dolares/oficial/'.$previous->format('Y/m/d'));
                    if ($response->successful() && $response->json('fecha') && $response->json('promedio')) {
                        $referenceDate = CarbonImmutable::parse($response->json('fecha'))->toDateString();
                        if ($referenceDate === $previous->toDateString()) {
                            return $this->save($date, (string) $response->json('promedio'), 'DolarApi · referencia anterior', $referenceDate);
                        }
                    }
                    if ($response->serverError()) {
                        break;
                    }
                }
            }
        } catch (ConnectionException|\InvalidArgumentException $exception) {
            report($exception);
        }

        if ($requested->equalTo($today)) {
            try {
                $response = Http::connectTimeout(3)->timeout(10)->get('https://www.bcv.org.ve/');
                if ($response->successful()) {
                    $document = new \DOMDocument;
                    $previousErrors = libxml_use_internal_errors(true);
                    $document->loadHTML($response->body());
                    libxml_clear_errors();
                    libxml_use_internal_errors($previousErrors);
                    $xpath = new \DOMXPath($document);
                    $amount = $xpath->evaluate('string(//div[@id="dolar"]//strong)');
                    $effective = $xpath->evaluate('string(//*[contains(@class,"date-display-single")]/@content)');
                    if ($effective && CarbonImmutable::parse($effective)->toDateString() === $date && trim($amount) !== '') {
                        $value = str_replace(',', '.', str_replace('.', '', trim($amount)));

                        return $this->save($date, $value, 'BCV · sitio oficial', $date);
                    }
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        throw ValidationException::withMessages(['rate' => 'No se pudo verificar la tasa de esa fecha. Puedes registrar la tasa manualmente con su motivo.']);
    }

    private function save(string $date, string $value, string $source, string $reference): ExchangeRate
    {
        $rate = Money::rate($value);
        if ($rate <= 0) {
            throw ValidationException::withMessages(['rate' => 'La tasa recibida debe ser positiva.']);
        }

        return ExchangeRate::firstOrCreate(['date' => $date], [
            'rate_micros' => $rate,
            'source' => $source,
            'reference_date' => $reference,
            'fetched_at' => now(),
        ]);
    }
}
