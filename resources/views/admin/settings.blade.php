@extends('layouts.app')
@section('title', 'Configuración de la clínica')
@section('page-title', 'Configuración')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 fw-bold">Configuración de la clínica</h1>
        <p class="text-secondary mb-0">Ajusta los datos generales de VetCare. Cada cambio queda registrado en el historial de auditoría y requiere confirmación de contraseña.</p>
    </div>
</div>

<div class="vet-card p-4">
    <form data-confirmed-form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($settings) }}">

        <div class="alert alert-danger d-none" data-form-errors role="alert"></div>

        <div data-edit-fields>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="settings-name">Nombre de la clínica <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="name" id="settings-name" value="{{ $settings->name }}" required maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="settings-phone">Teléfono</label>
                    <input class="form-control" type="text" name="phone" id="settings-phone" value="{{ $settings->phone }}" maxlength="30">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="settings-email">Correo electrónico</label>
                    <input class="form-control" type="email" name="email" id="settings-email" value="{{ $settings->email }}" maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="settings-appointment-minutes">Duración predeterminada de citas <span class="text-danger">*</span></label>
                    <select class="form-select" name="appointment_minutes" id="settings-appointment-minutes" required>
                        @foreach ([15 => '15 minutos', 20 => '20 minutos', 30 => '30 minutos', 45 => '45 minutos', 60 => '60 minutos'] as $mins => $label)
                            <option value="{{ $mins }}" @selected($settings->appointment_minutes == $mins)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="settings-address">Dirección</label>
                    <textarea class="form-control" name="address" id="settings-address" rows="2" maxlength="255">{{ $settings->address }}</textarea>
                </div>
                <div class="col-12"><hr class="my-1"><h2 class="h5 mt-3">Contacto y redes de la página principal</h2><p class="text-secondary small mb-0">Deja un campo vacío para ocultar ese acceso en la página pública.</p></div>
                <div class="col-md-6">
                    <label class="form-label" for="settings-facebook">Facebook</label>
                    <input class="form-control" type="url" name="facebook_url" id="settings-facebook" value="{{ $settings->facebook_url }}" maxlength="255" placeholder="https://facebook.com/vetcare">
                    <div class="form-text">Enlace HTTPS completo del perfil.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="settings-instagram">Instagram</label>
                    <input class="form-control" type="url" name="instagram_url" id="settings-instagram" value="{{ $settings->instagram_url }}" maxlength="255" placeholder="https://instagram.com/vetcare">
                    <div class="form-text">Enlace HTTPS completo del perfil.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="settings-tiktok">TikTok</label>
                    <input class="form-control" type="url" name="tiktok_url" id="settings-tiktok" value="{{ $settings->tiktok_url }}" maxlength="255" placeholder="https://tiktok.com/@vetcare">
                    <div class="form-text">Enlace HTTPS completo del perfil.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="settings-whatsapp">WhatsApp</label>
                    <input class="form-control" type="text" name="whatsapp_number" id="settings-whatsapp" value="{{ $settings->whatsapp_number }}" maxlength="15" inputmode="numeric" pattern="[1-9][0-9]{7,14}" data-numeric-only data-maxlength="15" placeholder="51999999999">
                    <div class="form-text">Código de país y número, solo dígitos. Ejemplo: 51999999999.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="settings-password">Confirma tu contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="current_password" id="settings-password" autocomplete="current-password" required>
                    <div class="form-text">Este cambio quedará registrado en el historial de auditoría.</div>
                </div>
            </div>
        </div>

        <div class="d-none mt-3" data-confirmation>
            <p class="fw-semibold">Confirma los datos revisados por el servidor</p>
            <dl class="row mb-0" data-summary></dl>
            <p class="small text-secondary mt-3">Al confirmar se consultarán nuevamente los datos y permisos. Esta autorización vence en 5 minutos.</p>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary d-none" data-back-to-edit>Volver a editar</button>
            <button type="submit" class="btn btn-primary" data-save-button>Revisar y continuar</button>
        </div>
    </form>
</div>
@endsection
