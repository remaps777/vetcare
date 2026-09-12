<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Boleta {{ $sale->sale_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .center { text-align: center; }
        .muted { color: #6b7280; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border-bottom: 1px solid #d1d5db; padding: 7px 5px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
        .total { font-size: 16px; font-weight: bold; margin-top: 18px; text-align: right; }
    </style>
</head>
<body>
    <div class="center">
        <h1>{{ $clinic?->name ?? 'Veterinaria' }}</h1>
        <div class="muted">Boleta de venta</div>
        <strong>Venta N° {{ $sale->sale_number }}</strong>
        <div>{{ $sale->sold_at->format('d/m/Y H:i') }}</div>
    </div>
    <p><strong>Cliente:</strong> {{ $sale->owner?->full_name ?? 'Cliente general' }}</p>
    @if($sale->owner)<p><strong>DNI:</strong> {{ $sale->owner->dni }}</p>@endif
    <p><strong>Atendido por:</strong> {{ $sale->seller->name }}</p>
    <table>
        <thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th class="right">Subtotal</th></tr></thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr><td>{{ $item->product->name }}</td><td>{{ $item->quantity }}</td><td>S/ {{ number_format($item->unit_price_cents / 100, 2) }}</td><td class="right">S/ {{ number_format($item->subtotal_cents / 100, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="total">TOTAL: S/ {{ $sale->total }}</div>
</body>
</html>
