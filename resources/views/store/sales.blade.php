@extends('layouts.app')
@section('title', 'Ventas')
@section('page-title', 'Tienda · Ventas')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 fw-bold">Ventas</h1><p class="text-secondary mb-0">Historial de ventas realizadas por ti.</p></div><a href="{{ route('store.cashier') }}" class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Nueva venta</a></div>
<div class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Artículos</th><th>Total</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($sales as $sale)<tr><td>{{ $sale->sale_number }}</td><td>{{ $sale->sold_at->format('d/m/Y H:i') }}</td><td>{{ $sale->owner?->full_name ?? 'Cliente general' }}</td><td>{{ $sale->seller->name }}</td><td>{{ $sale->items->sum('quantity') }}</td><td>S/ {{ $sale->total }}</td><td><span class="badge bg-success">Completada</span></td><td><button class="btn btn-sm btn-primary" title="Ver comprobante" data-bs-toggle="modal" data-bs-target="#receiptModal{{ $sale->id }}"><i class="bi bi-receipt"></i></button></td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">No hay ventas registradas.</td></tr>@endforelse</tbody></table></div></div></div>
@foreach($sales as $sale) @include('store.receipt-modal', ['sale' => $sale, 'clinic' => $clinic]) @endforeach
@endsection
