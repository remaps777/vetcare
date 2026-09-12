@extends('layouts.app')
@section('title', 'Permisos')
@section('page-title', 'Permisos de {{ $user->name }}')
@section('content')
<div class="card border-0 shadow-sm"><div class="card-body">
    <p>Perfil heredado: <strong>{{ $user->profile?->name ?? 'Sin perfil' }}</strong></p>
    @foreach($permissions as $module => $items)
        <h5 class="mt-4 text-uppercase">{{ $module }}</h5>
        <div class="table-responsive"><table class="table align-middle">
            @foreach($items as $item)
            <tr><td>{{ $item['name'] }} <small class="text-muted">({{ $item['code'] }})</small></td>
                <td><span class="badge text-bg-{{ $item['override'] ? ($item['override'] === 'ALLOW' ? 'success' : 'danger') : ($item['inherited'] ? 'info' : 'secondary') }}">{{ $item['override'] ? 'Personalizado: '.$item['override'] : ($item['inherited'] ? 'Heredado del perfil' : 'Sin acceso') }}</span></td>
                <td><form method="POST" action="{{ route('admin.users.permissions.update', $user) }}" class="d-flex gap-2 justify-content-end">@csrf @method('PATCH')<input type="hidden" name="permission_id" value="{{ $item['id'] }}"><select name="effect" class="form-select form-select-sm w-auto"><option value="INHERIT">Heredar</option><option value="ALLOW" @selected($item['override'] === 'ALLOW')>Permitir</option><option value="DENY" @selected($item['override'] === 'DENY')>Denegar</option></select><button class="btn btn-sm btn-primary">Guardar</button></form></td>
            </tr>
            @endforeach
        </table></div>
    @endforeach
</div></div>
@endsection
