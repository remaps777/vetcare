<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_permissions_are_inherited_by_the_user(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user->update([
            'account_type' => User::ACCOUNT_TYPE_USER,
            'profile_id' => Profile::where('code', 'CAJA')->value('id'),
        ]);

        $this->assertTrue($user->fresh()->hasPermission('ventas.crear'));
        $this->assertFalse($user->fresh()->hasPermission('productos.crear'));
    }

    public function test_explicit_deny_overrides_a_profile_permission(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DOCTOR]);
        $user->update(['profile_id' => Profile::where('code', 'DOCTOR')->value('id')]);
        $permission = Permission::where('code', 'medicamentos.dispensar')->firstOrFail();

        $user->permissionOverrides()->create([
            'permission_id' => $permission->id,
            'effect' => UserPermissionOverride::DENY,
        ]);

        $this->assertFalse($user->fresh()->hasPermission('medicamentos.dispensar'));
    }

    public function test_explicit_allow_grants_a_permission_not_in_the_profile(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user->update(['profile_id' => Profile::where('code', 'CAJA')->value('id')]);
        $permission = Permission::where('code', 'inventario.movimientos.ver')->firstOrFail();

        $user->permissionOverrides()->create([
            'permission_id' => $permission->id,
            'effect' => UserPermissionOverride::ALLOW,
        ]);

        $this->assertTrue($user->fresh()->hasPermission('inventario.movimientos.ver'));
    }

    public function test_bulk_permission_sync_creates_exceptions_and_restores_inheritance(): void
    {
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $actor->update(['profile_id' => Profile::where('code', 'ADMINISTRADOR')->value('id')]);
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user->update(['profile_id' => Profile::where('code', 'CAJA')->value('id')]);
        $service = app(PermissionService::class);
        $extra = Permission::where('code', 'inventario.movimientos.ver')->firstOrFail();
        $sales = Permission::where('code', 'ventas.crear')->firstOrFail();

        $service->syncUserPermissions($user, [$extra->id], $actor);

        $this->assertTrue($user->fresh()->hasPermission($extra->code));
        $this->assertFalse($user->fresh()->hasPermission($sales->code));
        $this->assertDatabaseHas('user_permission_overrides', ['user_id' => $user->id, 'permission_id' => $extra->id, 'effect' => UserPermissionOverride::ALLOW]);
        $this->assertDatabaseHas('user_permission_overrides', ['user_id' => $user->id, 'permission_id' => $sales->id, 'effect' => UserPermissionOverride::DENY]);

        $service->syncUserPermissions($user, $user->profile->permissions()->pluck('permissions.id')->all(), $actor);

        $this->assertDatabaseMissing('user_permission_overrides', ['user_id' => $user->id]);
    }

    public function test_user_without_permission_cannot_open_user_management(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user->update(['profile_id' => Profile::where('code', 'PROPIETARIO')->value('id')]);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_administrator_sees_the_single_users_page_with_action_menu(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $admin->update(['profile_id' => Profile::where('code', 'ADMINISTRADOR')->value('id')]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Administración de usuarios')
            ->assertSee('Editar datos')
            ->assertSee('Permisos')
            ->assertDontSee('href="/admin/users/'.$admin->id.'/edit"', false);
    }

    public function test_permission_page_can_select_an_entire_module_block(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $admin->update(['profile_id' => Profile::where('code', 'ADMINISTRADOR')->value('id')]);
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($admin)->get('/admin/users/'.$user->id.'/permissions')
            ->assertOk()
            ->assertSee('Seleccionar bloque')
            ->assertSee('data-permission-module="citas"', false)
            ->assertSee('citas.editar');
    }

    public function test_users_page_uses_edit_for_profile_and_status_actions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $admin->update(['profile_id' => Profile::where('code', 'ADMINISTRADOR')->value('id')]);
        User::factory()->create(['name' => 'Trabajador de prueba']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Nuevo trabajador')
            ->assertSee('Solicitudes de trabajadores')
            ->assertSee('Editar')
            ->assertSee('Restaurar contraseña')
            ->assertSee('Permisos')
            ->assertDontSee('Filtrar')
            ->assertDontSee('Cambiar perfil')
            ->assertDontSee('toggleUserModal');
    }
}
