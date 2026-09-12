<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <span class="stat-icon bg-primary-subtle-vet text-primary-vet"><i class="bi bi-person-circle"></i></span>
                    <div><h2 class="h4 fw-bold mb-1">Mi perfil</h2><p class="text-secondary mb-0">Mantén actualizada tu información de contacto.</p></div>
                </div>
                <form method="POST" action="{{ route($user->isDoctor() ? 'doctor.profile.update' : 'owner.profile.update') }}" class="row g-3">
                    @csrf @method('PATCH')
                    <div class="col-md-6"><label class="form-label">Nombre completo</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Correo electrónico</label><input class="form-control" type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" name="phone" value="{{ old('phone', $user->phone) }}"></div>
                    @if($user->owner)
                        <div class="col-md-6"><label class="form-label">Nombres</label><input class="form-control" name="first_name" value="{{ old('first_name', $user->owner->first_name) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Apellidos</label><input class="form-control" name="last_name" value="{{ old('last_name', $user->owner->last_name) }}" required></div>
                        <div class="col-md-6"><label class="form-label">DNI</label><input class="form-control" value="{{ $user->owner->dni }}" readonly></div>
                        <div class="col-12"><label class="form-label">Dirección / ubicación</label><input class="form-control" name="address" value="{{ old('address', $user->owner->address) }}"></div>
                    @endif
                    @if($user->doctorProfile)
                        <div class="col-md-6"><label class="form-label">DNI</label><input class="form-control" value="{{ $user->doctorProfile->dni }}" readonly></div>
                        <div class="col-md-6"><label class="form-label">Número de colegiatura</label><input class="form-control" value="{{ $user->doctorProfile->license_number }}" readonly></div>
                        <div class="col-md-6"><label class="form-label">Especialidad</label><select class="form-select" name="specialty_id">@foreach($specialties as $specialty)<option value="{{ $specialty->id }}" @selected($user->doctorProfile->specialty_id === $specialty->id)>{{ $specialty->name }}</option>@endforeach</select></div>
                    @endif
                    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Guardar cambios</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <h2 class="h5 fw-bold">Resumen de cuenta</h2>
            <dl class="row mb-0 mt-3"><dt class="col-5 text-secondary">Usuario</dt><dd class="col-7">{{ $user->username }}</dd><dt class="col-5 text-secondary">Perfil</dt><dd class="col-7">{{ $user->profile?->name ?? 'Sin perfil' }}</dd><dt class="col-5 text-secondary">Estado</dt><dd class="col-7"><span class="badge text-bg-success">Activo</span></dd></dl>
        </div></div>
    </div>
</div>
