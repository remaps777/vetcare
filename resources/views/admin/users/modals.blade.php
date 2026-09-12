<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><form method="POST" data-user-edit-form>
        @csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Editar datos</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="name" required></div>
            <div class="mb-3"><label class="form-label">Correo</label><input class="form-control" type="email" name="email" required></div>
            <div class="mb-3"><label class="form-label">Teléfono</label><input class="form-control" name="phone" inputmode="numeric"></div>
            <div class="mb-3"><label class="form-label">Perfil</label><select class="form-select" name="profile_id" required>@foreach($profiles as $profile)<option value="{{ $profile->id }}">{{ $profile->name }}</option>@endforeach</select></div>
            <div><label class="form-label">Estado</label><select class="form-select" name="is_active" required><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="permissionsUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><form method="POST" data-permissions-form>
        @csrf @method('PUT')
        <div class="modal-header"><div><h5 class="modal-title">Permisos de <span data-permissions-user></span></h5><small class="text-muted">Los permisos iniciales provienen del perfil. Puedes marcar o desmarcar accesos específicos.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="alert alert-danger d-none" data-permissions-error></div><div class="row g-3" data-permissions-list><div class="text-muted">Cargando permisos...</div></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar cambios</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="passwordUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><form method="POST" data-password-form>
        @csrf
        <div class="modal-header"><h5 class="modal-title">Restaurar contraseña</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @if(session('temporary_password'))
                <div data-password-confirm class="d-none"></div>
                <div data-password-result><p>Contraseña temporal:</p><div class="input-group"><input class="form-control" readonly data-temporary-password value="{{ session('temporary_password') }}"><button type="button" class="btn btn-outline-secondary" data-copy-password>Copiar</button></div><small class="text-muted">Esta contraseña solo se mostrará una vez.</small></div>
            @else
                <div data-password-confirm><p>¿Restaurar contraseña de <strong data-password-user></strong>?</p></div>
                <div class="d-none" data-password-result><p>Contraseña temporal:</p><div class="input-group"><input class="form-control" readonly data-temporary-password><button type="button" class="btn btn-outline-secondary" data-copy-password>Copiar</button></div><small class="text-muted">Esta contraseña solo se mostrará una vez.</small></div>
            @endif
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>@if(! session('temporary_password'))<button class="btn btn-warning" data-password-submit>Restaurar</button>@endif</div>
    </form></div></div>
</div>
@if(session('temporary_password'))
<script>
    document.addEventListener('DOMContentLoaded', () => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('passwordUserModal')).show();
    });
</script>
@endif
