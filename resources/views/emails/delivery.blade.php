<!doctype html>
<html lang="es"><head><meta charset="UTF-8"><title>{{ $delivery->subject }}</title></head>
<body style="font-family:Arial,sans-serif;color:#233e36;max-width:640px;margin:30px auto;padding:20px">
<h1 style="font-size:24px">{{ $delivery->subject }}</h1>
<p style="white-space:pre-line;line-height:1.7">{{ $delivery->body }}</p>
@if($delivery->charge_id)<p>El aviso de cobro individual se adjunta al correo.</p>@endif
<p style="color:#66776f">Administración del condominio</p>
</body></html>
