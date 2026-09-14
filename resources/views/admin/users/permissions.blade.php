@extends('layouts.app')
@section('title', 'Permisos')
@section('page-title', 'Permisos de {{ $user->name }}')
@section('content')
<div class="card border-0 shadow-sm"><div class="card-body">
    <p>Perfil heredado: <strong>{{ $user->profile?->name ?? 'Sin perfil' }}</strong></p>
    <form method="POST" action="{{ route('admin.users.permissions.sync', $user) }}">
        @csrf @method('PUT')
        @foreach($permissions as $module => $items)
        <div class="d-flex align-items-center justify-content-between mt-4">
            <h5 class="text-uppercase mb-0">{{ $module }}</h5>
            <label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-permission-module="{{ \Illuminate\Support\Str::slug($module) }}"><span class="form-check-label">Seleccionar bloque</span></label>
        </div>
        <div class="table-responsive"><table class="table align-middle">
            @foreach($items as $item)
            <tr><td><div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $item['id'] }}" data-permission-item="{{ \Illuminate\Support\Str::slug($module) }}" @checked($item['effective'])><label class="form-check-label">{{ $item['name'] }} <small class="text-muted">({{ $item['code'] }})</small></label></div></td>
                <td><span class="badge text-bg-{{ $item['override'] ? ($item['override'] === 'ALLOW' ? 'success' : 'danger') : ($item['inherited'] ? 'info' : 'secondary') }}">{{ $item['override'] ? 'Personalizado: '.$item['override'] : ($item['inherited'] ? 'Heredado del perfil' : 'Sin acceso') }}</span></td>
            </tr>
            @endforeach
        </table></div>
        @endforeach
        <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary">Guardar permisos seleccionados</button></div>
    </form>
</div></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-permission-module]').forEach((moduleCheckbox) => {
    moduleCheckbox.addEventListener('change', () => {
        document.querySelectorAll(`[data-permission-item="${moduleCheckbox.dataset.permissionModule}"]`).forEach((permission) => {
            permission.checked = moduleCheckbox.checked;
        });
    });
});
</script>
@endpush
