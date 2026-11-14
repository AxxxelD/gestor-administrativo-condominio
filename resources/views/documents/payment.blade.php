@extends('documents.layout', ['title' => 'Comprobante de pago'])
@section('content')
@php $money = fn ($cents) => number_format($cents / 100, 2, ',', '.'); @endphp
<h2>Comprobante de pago {{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</h2>
<p>Fecha de transferencia {{ $payment->paid_on->format('d/m/Y') }}</p>
<p>Referencia bancaria {{ $payment->reference }}</p>
@if($payment->rate_source)<p>Fuente de la tasa {{ $payment->rate_source }} · Fecha de referencia {{ $payment->rate_reference_date?->format('d/m/Y') }}</p>@endif
<p>Estado {{ $payment->status === 'posted' ? 'Registrado' : 'Anulado' }}</p>
<table>
<tr><th>Recibido en bolívares</th><th>Tasa utilizada Bs / USD</th><th>Equivalente USD</th></tr>
<tr><td>{{ $money($payment->amount_bs_cents) }}</td><td>{{ number_format($payment->rate_micros / 1000000, 6, ',', '.') }}</td><td>{{ $money($payment->amount_usd_cents) }}</td></tr>
</table>
<h2>Distribución entre inmuebles</h2>
<table><tr><th>TH</th><th class="number">USD</th></tr>
@foreach($payment->credits as $credit)<tr><td>{{ $credit->property->code }}</td><td class="number">{{ $money($credit->amount_usd_cents) }}</td></tr>@endforeach
</table>
@if($payment->status === 'voided')<p>Anulación {{ $payment->void_reason }}</p>@endif
@endsection
