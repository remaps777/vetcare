<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WorkerApplication;
use App\Services\RecordVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkerApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $code = 'ADMINISTRADOR'): User
    {
        return User::factory()->create(['profile_id' => Profile::where('code', $code)->value('id')]);
    }

    private function payload(string $code = 'CAJA'): array
    {
        return [
            'first_name' => 'Ana', 'last_name' => 'Torres', 'dni' => '12345678', 'phone' => '987654321',
            'email' => 'ana@example.test', 'username' => 'ana', 'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!',
            'requested_profile_id' => Profile::where('code', $code)->value('id'),
        ];
    }

    private function review(WorkerApplication $application, string $action, array $data = []): TestResponse
    {
        return $this->patch('/admin/worker-applications/'.$application->id, [
            'action' => $action, '_version' => RecordVersion::of($application->fresh()),
            'profile_id' => $application->requested_profile_id, ...$data,
        ]);
    }

    public static function operationalProfiles(): array
    {
        return [['CAJA'], ['INVENTARIO']];
    }

    #[DataProvider('operationalProfiles')]
    public function test_non_doctor_application_needs_no_professional_fields_and_creates_no_user(string $profile): void
    {
        $this->post('/register/worker', [...$this->payload($profile), 'role' => 'ADMIN', 'status' => 'APPROVED', 'is_active' => true, 'user_id' => 45])
            ->assertRedirect('/login')->assertSessionHasNoErrors();

        $application = WorkerApplication::firstOrFail();
        $this->assertSame('PENDING', $application->status);
        $this->assertNull($application->user_id);
        $this->assertNull($application->specialty_id);
        $this->assertTrue(Hash::check('SecurePass123!', $application->password));
        $this->assertArrayNotHasKey('password', $application->toArray());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'WORKER_APPLICATION_CREATED', 'user_id' => null]);
        $this->post('/login', ['login' => 'ana', 'password' => 'SecurePass123!'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_doctor_application_stores_active_specialty_and_five_digit_license(): void
    {
        $specialty = Specialty::firstOrCreate(['name' => 'Cirugía'], ['is_active' => true]);
        $this->post('/register/worker', [...$this->payload('DOCTOR'), 'specialty_id' => $specialty->id, 'license_number' => '00123'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('worker_applications', ['specialty_id' => $specialty->id, 'license_number' => '00123', 'status' => 'PENDING']);
        $this->assertDatabaseCount('doctor_profiles', 0);
    }

    public static function invalidLicenses(): array
    {
        return [['123456'], ['ABC12'], ['-123'], ['1.23']];
    }

    #[DataProvider('invalidLicenses')]
    public function test_invalid_license_is_rejected(string $license): void
    {
        $specialty = Specialty::firstOrCreate(['name' => 'Cirugía'], ['is_active' => true]);
        $this->post('/register/worker', [...$this->payload('DOCTOR'), 'specialty_id' => $specialty->id, 'license_number' => $license])->assertSessionHasErrors('license_number');
        $this->assertDatabaseCount('worker_applications', 0);
    }

    public function test_inactive_specialty_and_public_administrator_are_rejected(): void
    {
        $specialty = Specialty::create(['name' => 'Inactiva', 'is_active' => false]);
        $this->post('/register/worker', [...$this->payload('DOCTOR'), 'specialty_id' => $specialty->id, 'license_number' => '12345'])->assertSessionHasErrors('specialty_id');
        $this->post('/register/worker', $this->payload('ADMINISTRADOR'))->assertSessionHasErrors('requested_profile_id');
        $this->assertDatabaseCount('worker_applications', 0);
        $this->get('/register/worker')->assertDontSee('Administrador')->assertDontSee('Inactiva');
        $this->get('/register/doctor')->assertRedirect('/register/worker');
    }

    public function test_approval_creates_active_account_inherits_profile_and_erases_application_hash(): void
    {
        $application = WorkerApplication::factory()->create();
        $this->actingAs($this->actor());
        $this->review($application, 'approve')->assertSessionHasNoErrors();

        $application->refresh();
        $user = $application->user;
        $this->assertTrue($user->is_active);
        $this->assertSame('APPROVED', $application->status);
        $this->assertSame($application->requested_profile_id, $application->assigned_profile_id);
        $this->assertTrue($user->hasPermission('ventas.crear'));
        $this->assertFalse($user->hasPermission('solicitudes_trabajador.ver'));
        $this->assertDatabaseCount('user_permission_overrides', 0);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertNull($application->password);
        $this->assertStringNotContainsString('SecurePass123!', AuditLog::all()->toJson());
        $this->assertStringNotContainsString($user->password, AuditLog::all()->toJson());
        $this->review($application, 'approve')->assertConflict();
        $this->assertDatabaseCount('users', 2);
        $this->post('/logout');
        $this->post('/login', ['login' => $user->username, 'password' => 'SecurePass123!'])->assertRedirect('/store/cashier');
        $this->get('/store/cashier')->assertOk();
        $this->get('/owner/dashboard')->assertForbidden();
    }

    public function test_approval_can_assign_another_profile_with_audit(): void
    {
        $application = WorkerApplication::factory()->create(['requested_profile_id' => Profile::where('code', 'INVENTARIO')->value('id')]);
        $assigned = Profile::where('code', 'INVENTARIO')->firstOrFail();
        $this->actingAs($this->actor());
        $this->review($application, 'approve', ['profile_id' => $assigned->id])->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('INVENTARIO', $application->requestedProfile->code);
        $this->assertSame('INVENTARIO', $application->assignedProfile->code);
        $entry = $application->history()->where('action', 'WORKER_APPLICATION_APPROVED')->firstOrFail();
        $this->assertSame($assigned->id, $entry->after_data['assigned_profile_id']);
    }

    public function test_doctor_approval_revalidates_specialty_then_creates_professional_profile(): void
    {
        $specialty = Specialty::create(['name' => 'Clínica', 'is_active' => false]);
        $application = WorkerApplication::factory()->create(['requested_profile_id' => Profile::where('code', 'DOCTOR')->value('id'), 'specialty_id' => $specialty->id, 'license_number' => '12345']);
        $this->actingAs($this->actor());
        $this->review($application, 'approve')->assertSessionHasErrors('specialty_id');
        $this->assertDatabaseCount('users', 1);
        $specialty->update(['is_active' => true]);
        $this->review($application, 'approve')->assertSessionHasNoErrors();
        $user = $application->fresh()->user;
        $this->assertDatabaseHas('doctor_profiles', ['user_id' => $user->id, 'specialty_id' => $specialty->id, 'approval_status' => 'APPROVED']);
        $this->assertNull($user->accessDeniedReason());
        $this->actingAs($user)->get('/doctor/dashboard')->assertOk();
    }

    public static function decisions(): array
    {
        return [
            ['PENDING', 'hold', 'ON_HOLD'], ['PENDING', 'reject', 'REJECTED'],
            ['ON_HOLD', 'reject', 'REJECTED'], ['REJECTED', 'reconsider', 'PENDING'],
            ['ON_HOLD', 'approve', 'APPROVED'], ['REJECTED', 'approve', 'APPROVED'],
        ];
    }

    #[DataProvider('decisions')]
    public function test_status_transitions_preserve_application_and_review_history(string $from, string $action, string $to): void
    {
        $application = WorkerApplication::factory()->create(['status' => $from]);
        $actor = $this->actor();
        $this->actingAs($actor);
        $this->review($application, $action, ['review_notes' => 'Datos revisados.'])->assertSessionHasNoErrors();
        $application->refresh();

        $this->assertSame($to, $application->status);
        $this->assertSame($actor->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame('Datos revisados.', $application->review_notes);
        $this->assertDatabaseCount('worker_applications', 1);
        $this->assertSame($from, $application->history()->firstOrFail()->before_data['status']);
        $this->assertSame($to, $application->history()->firstOrFail()->after_data['status']);
    }

    public function test_stale_review_cannot_overwrite_another_decision(): void
    {
        $application = WorkerApplication::factory()->create();
        $version = RecordVersion::of($application);
        $this->actingAs($this->actor());
        $this->review($application, 'hold')->assertSessionHasNoErrors();
        $this->review($application, 'reject', ['_version' => $version])->assertConflict();
        $this->assertSame('ON_HOLD', $application->fresh()->status);
    }

    public static function unprivilegedProfiles(): array
    {
        return [['PROPIETARIO'], ['CAJA'], ['INVENTARIO']];
    }

    #[DataProvider('unprivilegedProfiles')]
    public function test_profiles_cannot_read_or_review_applications_by_default(string $profile): void
    {
        $application = WorkerApplication::factory()->create();
        $this->actingAs($this->actor($profile))->get('/admin/worker-applications')->assertForbidden();
        $this->review($application, 'approve')->assertForbidden();
        $this->assertSame('PENDING', $application->fresh()->status);
    }

    public function test_explicit_override_allows_review_without_granting_profile_changes(): void
    {
        $application = WorkerApplication::factory()->create();
        $user = $this->actor('CAJA');
        foreach (['solicitudes_trabajador.ver', 'solicitudes_trabajador.aprobar'] as $code) {
            $user->permissionOverrides()->create(['permission_id' => Permission::where('code', $code)->value('id'), 'effect' => 'ALLOW']);
        }
        $this->actingAs($user)->get('/admin/worker-applications')->assertOk();
        $this->review($application, 'reject')->assertForbidden();
        $this->review($application, 'approve', ['profile_id' => Profile::where('code', 'ADMINISTRADOR')->value('id')])->assertForbidden();
        $this->review($application, 'approve')->assertSessionHasNoErrors();
    }

    public function test_guest_cannot_access_management(): void
    {
        $this->get('/admin/worker-applications')->assertRedirect('/login');
        $this->get('/admin/users/create')->assertRedirect('/login');
    }

    public function test_admin_can_create_internal_worker_without_application(): void
    {
        $this->actingAs($this->actor());
        $payload = $this->payload('INVENTARIO');
        $this->post('/admin/users', [...$payload, 'profile_id' => $payload['requested_profile_id']])->assertRedirect('/admin/users')->assertSessionHasNoErrors();

        $user = User::where('username', 'ana')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertSame('12345678', $user->dni);
        $this->assertTrue($user->hasPermission('inventario.ingreso'));
        $this->assertDatabaseCount('worker_applications', 0);
        $this->assertSame('admin.inventory.index', $user->dashboardRoute());
    }

    public function test_internal_admin_creation_requires_permission_management(): void
    {
        $actor = $this->actor();
        $actor->permissionOverrides()->create(['permission_id' => Permission::where('code', 'usuarios.cambiar_permisos')->value('id'), 'effect' => 'DENY']);
        $payload = $this->payload();
        $this->actingAs($actor)->post('/admin/users', [...$payload, 'profile_id' => Profile::where('code', 'ADMINISTRADOR')->value('id')])->assertSessionHasErrors('profile_id');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_reconsider_does_not_require_registration_and_keeps_previous_notes(): void
    {
        $application = WorkerApplication::factory()->create();
        $this->actingAs($this->actor());
        $this->review($application, 'reject', ['review_notes' => 'Datos incompletos.']);
        $this->review($application, 'reconsider', ['review_notes' => 'Se verificaron los datos.']);
        $this->review($application, 'approve')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('worker_applications', 1);
        $this->assertSame(['REJECTED', 'PENDING', 'APPROVED'], $application->history()->get()->map(fn ($entry) => $entry->after_data['status'])->all());
        $this->assertSame('Datos incompletos.', $application->history()->first()->after_data['review_notes']);
    }

    public function test_rejected_application_can_be_reaccepted_from_history(): void
    {
        $application = WorkerApplication::factory()->create();
        $this->actingAs($this->actor());

        $this->review($application, 'reject')->assertSessionHasNoErrors();
        $this->review($application->fresh(), 'reaccept')->assertSessionHasNoErrors();

        $this->assertSame('APPROVED', $application->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'subject_id' => $application->id,
            'action' => 'WORKER_APPLICATION_APPROVED',
        ]);
    }

    public function test_linked_legacy_account_is_reactivated_without_losing_overrides(): void
    {
        $user = User::factory()->create(['role' => 'DOCTOR', 'is_active' => false]);
        $doctor = $user->doctorProfile()->create(['dni' => '12345678', 'license_number' => '12345', 'phone' => '987654321']);
        $application = WorkerApplication::factory()->create(['user_id' => $user->id, 'dni' => $doctor->dni, 'email' => $user->email, 'username' => $user->username]);
        $user->permissionOverrides()->create(['permission_id' => Permission::where('code', 'ventas.crear')->value('id'), 'effect' => 'DENY']);
        $this->actingAs($this->actor());
        $this->patch('/admin/users/'.$user->id.'/toggle')->assertUnprocessable();
        $this->review($application, 'approve')->assertSessionHasNoErrors();

        $this->assertSame($user->id, $application->fresh()->user_id);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertFalse($user->fresh()->hasPermission('ventas.crear'));
        $this->assertDatabaseCount('users', 2);
        $this->assertModelExists($doctor);
    }

    public function test_last_administrator_is_protected_from_profile_and_permission_changes(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->patch('/admin/users/'.$actor->id.'/profile', ['profile_id' => Profile::where('code', 'CAJA')->value('id')])->assertSessionHasErrors('user');
        $this->patch('/admin/users/'.$actor->id.'/toggle')->assertSessionHasErrors('user');
        $this->put('/admin/users/'.$actor->id.'/permissions', ['permissions' => []])->assertSessionHasErrors('user');
        $this->patch('/admin/users/'.$actor->id.'/permissions', ['permission_id' => Permission::where('code', 'usuarios.cambiar_permisos')->value('id'), 'effect' => 'DENY'])->assertSessionHasErrors('user');

        $this->assertTrue($actor->fresh()->is_active);
        $this->assertTrue($actor->fresh()->hasPermission('usuarios.cambiar_permisos'));
        $this->assertSame('ADMINISTRADOR', $actor->fresh()->profile->code);
    }

    public function test_admin_dashboard_counts_and_filters_applications_and_escapes_notes(): void
    {
        WorkerApplication::factory()->create(['status' => 'ON_HOLD', 'first_name' => '<script>bad()</script>']);
        $this->actingAs($this->actor())->get('/admin/dashboard')->assertSee('Solicitudes de trabajadores')->assertSee('En espera');
        $this->get('/admin/worker-applications?status=ON_HOLD')->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
        $this->get('/admin/worker-applications?status=REJECTED')->assertDontSee('bad()');
        $this->get('/admin/users/create')->assertSee('Nuevo trabajador');
    }

    public function test_inactive_worker_cannot_login_or_continue_session_and_keeps_account(): void
    {
        $worker = $this->actor('CAJA');
        $admin = $this->actor();
        $this->actingAs($admin)->patch('/admin/users/'.$worker->id.'/toggle')->assertSessionHasNoErrors();
        $this->actingAs($worker->fresh())->get('/store/cashier')->assertRedirect('/login');
        $this->post('/login', ['login' => $worker->username, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertModelExists($worker);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $worker->id, 'action' => 'USER_DEACTIVATED']);
    }
}
