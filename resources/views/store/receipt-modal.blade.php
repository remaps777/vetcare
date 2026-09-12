<div class="modal fade" id="receiptModal{{ $sale->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5">Comprobante interno</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body" id="receipt{{ $sale->id }}">
            <div class="text-center mb-4"><h3 class="h4">{{ $clinic?->name ?? 'Veterinaria' }}</h3><div>Comprobante interno / boleta simple</div><strong>Venta N° {{ $sale->sale_number }}</strong><div>{{ $sale->sold_at->format('d/m/Y H:i') }}</div></div>
            <p class="mb-1"><strong>Cliente:</strong> {{ $sale->owner?->full_name ?? 'Cliente general' }}</p>
            @if($sale->owner)<p class="mb-1"><strong>DNI:</strong> {{ $sale->owner->dni }}</p>@endif
            <p><strong>Atendido por:</strong> {{ $sale->seller->name }}</p>
            <table class="table"><thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>@foreach($sale->items as $item)<tr><td>{{ $item->product->name }}</td><td>{{ $item->quantity }}</td><td>S/ {{ number_format($item->unit_price_cents / 100, 2) }}</td><td>S/ {{ number_format($item->subtotal_cents / 100, 2) }}</td></tr>@endforeach</tbody></table>
            <div class="text-end fs-5"><strong>TOTAL: S/ {{ $sale->total }}</strong></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button><a class="btn btn-primary" href="{{ route('store.sales.pdf', $sale) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Descargar boleta PDF</a></div>
    </div></div>
</div>
