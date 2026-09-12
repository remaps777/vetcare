@php
    $record = $record ?? null;
    $prefix = $prefix ?? 'worker';
    $showPassword = $showPassword ?? true;
    $useOld = ! $record || (string) old('_application') === (string) $record->id;
    $value = fn ($key) => $useOld ? old($key, data_get($record, $key)) : data_get($record, $key);
    $selectedProfile = $value($profileField) ?? $record?->requested_profile_id;
@endphp
<div class="row g-3" data-worker-fields>
    @foreach(['first_name' => ['Nombres', 'text', 100], 'last_name' => ['Apellidos', 'text', 100], 'dni' => ['DNI', 'text', 8], 'phone' => ['Teléfono', 'tel', 10], 'email' => ['Correo electrónico', 'email', 150], 'username' => ['Nombre de usuario', 'text', 50]] as $field => [$label, $type, $max])
        <div class="col-md-6">
            <label class="form-label" for="{{ $prefix }}-{{ $field }}">{{ $label }} <span class="text-danger">*</span></label>
            <input class="form-control" id="{{ $prefix }}-{{ $field }}" name="{{ $field }}" type="{{ $type }}" maxlength="{{ $max }}" value="{{ $value($field) }}" required @if(in_array($field, ['dni', 'phone'])) inputmode="numeric" pattern="[0-9]*" data-numeric-only data-maxlength="{{ $max }}" @endif>
            @if($useOld) @error($field)<div class="text-danger small">{{ $message }}</div>@enderror @endif
        </div>
    @endforeach
    <div class="col-12">
        <label class="form-label" for="{{ $prefix }}-profile">{{ $profileField === 'requested_profile_id' ? 'Puesto solicitado' : 'Perfil a asignar' }} <span class="text-danger">*</span></label>
        <select class="form-select" id="{{ $prefix }}-profile" name="{{ $profileField }}" data-worker-profile required>
            <option value="">Selecciona un puesto</option>
            @foreach($profiles as $profile)
                @if(! $record || auth()->user()->hasPermission('usuarios.cambiar_permisos') || (int) $profile->id === (int) $record->requested_profile_id)
                    <option value="{{ $profile->id }}" data-profile-code="{{ $profile->code }}" @selected((string) $selectedProfile === (string) $profile->id)>{{ $profile->name }}</option>
                @endif
            @endforeach
        </select>
        @if($useOld) @error($profileField)<div class="text-danger small">{{ $message }}</div>@enderror @endif
    </div>
    <fieldset class="col-12" data-doctor-fields>
        <legend class="h6">Datos profesionales del Doctor</legend>
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label" for="{{ $prefix }}-specialty">Especialidad <span class="text-danger">*</span></label>
                <select class="form-select" name="specialty_id" id="{{ $prefix }}-specialty" required>
                    <option value="">Selecciona una especialidad</option>
                    @foreach($specialties as $specialty)<option value="{{ $specialty->id }}" @selected((string) $value('specialty_id') === (string) $specialty->id)>{{ $specialty->name }}</option>@endforeach
                </select>
                @if($useOld) @error('specialty_id')<div class="text-danger small">{{ $message }}</div>@enderror @endif
            </div>
            <div class="col-md-5">
                <label class="form-label" for="{{ $prefix }}-license">Colegiatura <span class="text-danger">*</span></label>
                <input class="form-control" name="license_number" id="{{ $prefix }}-license" maxlength="5" inputmode="numeric" pattern="[0-9]{1,5}" data-numeric-only data-maxlength="5" value="{{ $value('license_number') }}" required>
                <div class="form-text">Solo números, máximo 5 dígitos.</div>
                @if($useOld) @error('license_number')<div class="text-danger small">{{ $message }}</div>@enderror @endif
            </div>
        </div>
    </fieldset>
    @if($showPassword)
        <div class="col-md-6">
            <label class="form-label" for="{{ $prefix }}-password">Contraseña <span class="text-danger">*</span></label>
            <input class="form-control" type="password" name="password" id="{{ $prefix }}-password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <div class="form-text">Mínimo 8 caracteres.</div>
            @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="{{ $prefix }}-confirmation">Confirmar contraseña <span class="text-danger">*</span></label>
            <input class="form-control" type="password" name="password_confirmation" id="{{ $prefix }}-confirmation" autocomplete="new-password" required>
        </div>
    @endif
</div>
