@extends('documents.layout', ['title' => 'Aviso de cobro'])
@section('content')
@php
    $money = fn ($cents) => number_format($cents / 100, 2, ',', '.');
    $categories = ['sector_fixed' => 'Gastos fijos Sector C', 'sector_variable' => 'Gastos variables Sector C', 'common_fixed' => 'Gastos comunes fijos', 'common_variable' => 'Gastos comunes variables'];
    $units = $period?->units_count ?: 1;
@endphp
<h2>Aviso de cobro {{ $period?->month ?? 'Saldo inicial' }}</h2>
<p>Documento {{ str_pad($charge->id, 6, '0', STR_PAD_LEFT) }} · Emitido {{ $charge->charged_on->format('d/m/Y') }}</p>
<p><strong>TH {{ $code }}</strong> · {{ $owners }}</p>
<table>
    <thead><tr><th>Concepto</th><th class="number">Total USD</th><th class="number">Por TH USD</th></tr></thead>
    <tbody>
    @foreach($categories as $key => $label)
        @php $group = collect($items)->where('category', $key); @endphp
        <tr class="category"><td colspan="3">{{ $label }}</td></tr>
        @foreach($group as $item)
        <tr><td>{{ $item['description'] }}</td><td class="number">{{ $money($item['amount_usd_cents']) }}</td><td class="number">{{ $money(\App\Services\Money::ratio($item['amount_usd_cents'], 1, $units)) }}</td></tr>
        @endforeach
        <tr><td>Total {{ $label }}</td><td class="number">{{ $money($group->sum('amount_usd_cents')) }}</td><td class="number">{{ $money(\App\Services\Money::ratio((int) $group->sum('amount_usd_cents'), 1, $units)) }}</td></tr>
    @endforeach
    @if($period)
    <tr><td>Base neta</td><td class="number">{{ $money($period->net_usd_cents) }}</td><td class="number">{{ $money($charge->principal_usd_cents) }}</td></tr>
    <tr><td>Fondo de reserva {{ $period->reserve_basis_points / 100 }} %</td><td class="number">{{ $money($period->reserve_usd_cents) }}</td><td class="number">{{ $money($charge->reserve_usd_cents) }}</td></tr>
    <tr><td>Ajuste global de redondeo del reparto</td><td class="number">{{ $money($period->rounding_adjustment_usd_cents) }}</td><td></td></tr>
    @endif
    <tr class="total"><td>Cuota de este aviso</td><td class="number">{{ $period ? $money($period->total_usd_cents + $period->rounding_adjustment_usd_cents) : '' }}</td><td class="number">USD {{ $money($charge->principal_usd_cents + $charge->reserve_usd_cents) }}</td></tr>
    </tbody>
</table>
@if($balance !== null)
<p style="margin-top:12px">Saldo de la cuenta al emitir este aviso <strong>USD {{ $money($balance) }}</strong>. Los importes negativos representan saldo a favor.</p>
@endif
<p class="notes">{{ $settings['bank_instructions'] ?? '' }}</p>
<p>La transferencia se realiza en bolívares a la tasa BCV aplicable en la fecha del pago. Conserva la referencia bancaria.</p>
<p class="notes">{{ $settings['notes'] ?? '' }}</p>
<p class="footer">Participación de 1/{{ $units }}. Los valores por concepto se presentan redondeados. La cuota final y el ajuste del reparto conservan el total emitido.</p>
@endsection
