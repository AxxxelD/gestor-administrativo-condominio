<?php

namespace Tests\Feature;

use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_current_rate_is_stored_with_precision_and_reused(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        Http::fake(['ve.dolarapi.com/*' => Http::response(['promedio' => 100.123456, 'fechaActualizacion' => '2026-11-13T00:00:00-04:00'])]);
        $service = app(ExchangeRateService::class);
        $rate = $service->lookup('2026-11-13');
        $this->assertSame(100123456, $rate->rate_micros);
        $this->assertSame($rate->id, $service->lookup('2026-11-13')->id);
        Http::assertSentCount(1);
    }

    public function test_unavailable_historical_rate_never_uses_todays_bcv_page(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13));
        Http::fake(['*' => Http::response([], 503)]);
        try {
            app(ExchangeRateService::class)->lookup('2026-11-11');
            $this->fail('Debe indicar que falta una tasa histórica verificada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rate', $exception->errors());
        }
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'bcv.org.ve'));
        $this->assertDatabaseCount('exchange_rates', 0);
    }

    public function test_weekend_search_retains_the_actual_reference_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16));
        Http::fake([
            '*/2026/11/15' => Http::response([], 404),
            '*/2026/11/14' => Http::response([], 404),
            '*/2026/11/13' => Http::response(['promedio' => 100, 'fecha' => '2026-11-13']),
        ]);
        $rate = app(ExchangeRateService::class)->lookup('2026-11-15');
        $this->assertSame('2026-11-15', $rate->date);
        $this->assertSame('2026-11-13', $rate->reference_date);
    }

    public function test_current_bcv_fallback_requires_matching_effective_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
        Http::fake([
            've.dolarapi.com/*' => Http::response([], 503),
            'www.bcv.org.ve/*' => Http::response('<div id="dolar"><strong>100,123456</strong></div><span class="date-display-single" content="2026-11-13T00:00:00-04:00"></span>'),
        ]);
        $rate = app(ExchangeRateService::class)->lookup('2026-11-13');
        $this->assertSame(100123456, $rate->rate_micros);
        $this->assertSame('BCV · sitio oficial', $rate->source);
    }
}
