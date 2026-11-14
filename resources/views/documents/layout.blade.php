<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 30px 34px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #233e36; font-size: 10px; line-height: 1.45; }
        h1 { font-size: 19px; margin: 0 0 6px; color: #183f33; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        p { margin: 4px 0; }
        .brand { margin-bottom: 20px; }
        .muted { color: #66776f; }
        .number { text-align: right; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { text-align: left; background: #e7eee8; padding: 7px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e1e7e2; }
        tr { page-break-inside: avoid; }
        .category td { background: #e8eab8; font-weight: bold; }
        .total td { background: #183f33; color: white; font-weight: bold; }
        .notes { white-space: pre-line; margin-top: 16px; }
        .footer { margin-top: 22px; color: #66776f; font-size: 9px; }
        .demo { color: #9b4e18; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>
    @if($settings['demo'] ?? false)<p class="demo">DEMOSTRACIÓN · Datos ficticios</p>@endif
    <div class="brand">
        <h1>{{ $settings['condominium_name'] }}</h1>
        <p>{{ $settings['rif'] ?? '' }}</p>
        <p>{{ $settings['contact_email'] ?? '' }}</p>
    </div>
    @yield('content')
</body>
</html>
