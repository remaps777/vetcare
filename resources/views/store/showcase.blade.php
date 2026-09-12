@extends('layouts.app')
@section('title', 'Escaparate')
@section('page-title', 'Tienda · Escaparate')
@section('content')
<div class="mb-4"><h1 class="h3 fw-bold">Escaparate</h1><p class="text-secondary mb-0">Productos disponibles en el Almacén Tienda.</p></div>
<div class="row g-4">@forelse($products as $product)<div class="col-md-6 col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><span class="badge bg-primary-subtle text-primary mb-2">{{ $product->type }}</span><h2 class="h5">{{ $product->name }}</h2><p class="text-secondary">{{ $product->presentation ?: 'Producto' }}</p><div class="d-flex justify-content-between align-items-center"><strong class="text-primary">S/ {{ $product->sale_price }}</strong><span class="small text-success">{{ $product->store_quantity }} disponibles</span></div></div></div></div>@empty<div class="col-12"><div class="alert alert-info">No hay productos disponibles en tienda.</div></div>@endforelse</div>
@endsection
