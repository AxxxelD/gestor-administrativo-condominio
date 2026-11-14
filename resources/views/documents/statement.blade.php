@extends('documents.layout', ['title' => 'Estado de cuenta'])
@section('content')
@php $money = fn ($cents) => number_format($cents / 100, 2, ',', '.'); @endphp
<h2>Estado de cuenta · {{ $property->code }}</h2>
<p>Consulta del {{ now()->format('d/m/Y H:i') }}</p>
<table><tr><th>Fecha</th><th>Cargo</th><th class="number">Importe USD</th><th class="number">Aplicado USD</th><th class="number">Pendiente USD</th></tr>
@foreach($state['charges'] as $charge)
<tr><td>{{ $charge->charged_on->format('d/m/Y') }}</td><td>{{ $charge->description }}</td><td class="number">{{ $money($charge->principal_usd_cents + $charge->reserve_usd_cents) }}</td><td class="number">{{ $money($charge->paid_usd_cents) }}</td><td class="number">{{ $money($charge->outstanding_usd_cents) }}</td></tr>
@endforeach
</table>
<h2>Pagos y créditos</h2>
<table><tr><th>Fecha</th><th>Origen</th><th class="number">Abono USD</th><th class="number">Disponible USD</th></tr>
@foreach($state['credits'] as $credit)
<tr><td>{{ $credit->credited_on->format('d/m/Y') }}</td><td>{{ $credit->payment?->reference ?? 'Saldo inicial a favor' }}</td><td class="number">{{ $money($credit->amount_usd_cents) }}</td><td class="number">{{ $money($credit->available_usd_cents) }}</td></tr>
@endforeach
</table>
<h2>Deuda pendiente USD {{ $money($state['debt_usd_cents']) }}</h2>
<p>Crédito disponible USD {{ $money($state['credit_usd_cents']) }}</p>
@endsection
