<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Breed;
use App\Models\Consultation;
use App\Models\DoctorProfile;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Species;
use App\Models\User;
use App\Models\WorkerApplication;
use App\Services\RecordVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function doctor(): DoctorProfile
    {
        $user = User::factory()->create(['role' => 'DOCTOR']);
        $profile = $user->doctorProfile()->create(['dni' => fake()->unique()->numerify('########'), 'license_number' => fake()->unique()->numerify('CMVP-#####')]);
        $profile->approve();

        return $profile;
    }

    private function owner(): Owner
    {
        return User::factory()->create()->owner()->create(['dni' => fake()->unique()->numerify('########'), 'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '987654321']);
    }

    private function pet(Owner $owner, string $name): Pet
    {
        $species = Species::firstOrCreate(['name' => 'Canino']);
        $breed = Breed::firstOrCreate(['species_id' => $species->id, 'name' => 'Mestizo']);

        return $owner->pets()->create(['name' => $name, 'species_id' => $species->id, 'breed_id' => $breed->id, 'sex' => 'MACHO']);
    }

    public static function publicPages(): array
    {
        return ['home' => ['/'], 'login' => ['/login'], 'owner' => ['/register/owner'], 'worker' => ['/register/worker']];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_render_local_assets(string $path): void
    {
        $this->get($path)->assertOk()->assertSee('/build/assets/app-', false)->assertDontSee('tailwindcss')->assertDontSee('cdn.jsdelivr.net');
    }

    public static function forbiddenPaths(): array
    {
        return [
            'owner admin' => ['OWNER', '/admin/dashboard'],
            'owner admin root' => ['OWNER', '/admin'],
            'owner doctor' => ['OWNER', '/doctor/dashboard'],
            'owner doctor root' => ['OWNER', '/doctor'],
            'owner approvals' => ['OWNER', '/admin/doctors/pending'],
            'owner admin placeholder' => ['OWNER', '/admin/pets'],
            'doctor admin' => ['DOCTOR', '/admin/dashboard'],
            'doctor admin placeholder' => ['DOCTOR', '/admin/users'],
        ];
    }

    #[DataProvider('forbiddenPaths')]
    public function test_roles_cannot_access_other_panels(string $role, string $path): void
    {
        $user = $role === 'DOCTOR' ? $this->doctor()->user : User::factory()->create();
        $this->actingAs($user)->get($path)->assertForbidden();
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_admin_dashboard_counts_real_records_and_ignores_cancelled_appointments(): void
    {
        $this->freezeTime();
        $pet = $this->pet($this->owner(), 'Luna');
        $doctor = $this->doctor();
        $doctor->user->is_active = false;
        $doctor->user->save();
        $doctor->approval_status = 'PENDING';
        $doctor->save();
        Appointment::create(['pet_id' => $pet->id, 'doctor_id' => $doctor->id, 'scheduled_at' => now()->addDay(), 'reason' => 'Control']);
        Appointment::create(['pet_id' => $pet->id, 'doctor_id' => $doctor->id, 'scheduled_at' => now()->addDay(), 'reason' => 'Cancelada', 'status' => 'CANCELLED']);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))->get('/admin/dashboard')
            ->assertOk()->assertViewHas('stats', ['Propietarios' => 1, 'Doctores' => 1, 'Mascotas' => 1, 'Citas próximas' => 1])
            ->assertDontSee('href="#"', false)->assertDontSee('bi-github');
    }

    public function test_owner_dashboard_never_exposes_another_owners_data(): void
    {
        $this->freezeTime();
        $owner = $this->owner();
        $pet = $this->pet($owner, 'Mi mascota');
        $other = $this->pet($this->owner(), 'Mascota privada ajena');
        $doctor = $this->doctor();
        foreach ([$pet, $other] as $patient) {
            Appointment::create(['pet_id' => $patient->id, 'doctor_id' => $doctor->id, 'scheduled_at' => now()->addDay(), 'reason' => $patient->name]);
            Consultation::create(['pet_id' => $patient->id, 'doctor_id' => $doctor->id, 'consulted_at' => now(), 'reason' => 'Control', 'diagnosis' => 'Saludable']);
        }

        $this->actingAs($owner->user)->get('/owner/dashboard?owner_id='.$other->owner_id)
            ->assertOk()->assertSee('Mi mascota')->assertDontSee('Mascota privada ajena')
            ->assertViewHas('stats', ['Mis mascotas' => 1, 'Mis próximas citas' => 1, 'Consultas en mi historial' => 1]);
    }

    public function test_owner_without_profile_does_not_see_unlinked_records(): void
    {
        $this->pet($this->owner(), 'Privada');
        $this->actingAs(User::factory()->create())->get('/owner/dashboard')
            ->assertOk()->assertDontSee('Privada')->assertViewHas('stats', ['Mis mascotas' => 0, 'Mis próximas citas' => 0, 'Consultas en mi historial' => 0]);
    }

    public function test_doctor_dashboard_only_counts_his_appointments_and_recent_consultations(): void
    {
        $this->freezeTime();
        $doctor = $this->doctor();
        $other = $this->doctor();
        $pet = $this->pet($this->owner(), 'Luna');
        Appointment::create(['pet_id' => $pet->id, 'doctor_id' => $doctor->id, 'scheduled_at' => now()->addMinutes(1), 'reason' => 'Mi cita']);
        Appointment::create(['pet_id' => $pet->id, 'doctor_id' => $other->id, 'scheduled_at' => now()->addMinutes(1), 'reason' => 'Cita ajena']);
        Consultation::create(['pet_id' => $pet->id, 'doctor_id' => $doctor->id, 'consulted_at' => now()->subDays(2), 'reason' => 'Control', 'diagnosis' => 'Saludable']);
        Consultation::create(['pet_id' => $pet->id, 'doctor_id' => $doctor->id, 'consulted_at' => now()->subDays(31), 'reason' => 'Control', 'diagnosis' => 'Saludable']);

        $this->actingAs($doctor->user)->get('/doctor/dashboard')->assertOk()->assertSee('Mi cita')->assertDontSee('Cita ajena')
            ->assertViewHas('stats', ['Citas de hoy' => 1, 'Próximas citas' => 1, 'Mascotas registradas' => 1, 'Consultas recientes' => 1]);
    }

    public function test_only_admin_and_doctor_can_schedule_appointments_while_owner_can_only_view_them(): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner, 'Luna');
        $doctor = $this->doctor();
        $otherDoctor = $this->doctor();
        $payload = [
            'owner_id' => $owner->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'reason' => 'Consulta preventiva',
        ];

        $this->actingAs($owner->user)->postJson('/owner/appointments', [
            ...$payload,
            '_preview' => '1',
        ])->assertMethodNotAllowed();
        $this->actingAs($owner->user)->get('/owner/appointments')
            ->assertOk()
            ->assertDontSee('Revisar y continuar');

        $this->actingAs($doctor->user)->postJson('/doctor/appointments', [
            ...$payload,
            'owner_id' => '',
            '_preview' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['owner_id']);

        $preview = $this->actingAs($doctor->user)->postJson('/doctor/appointments', [
            ...$payload,
            '_preview' => '1',
        ])->assertOk()->json();
        $this->actingAs($doctor->user)->postJson('/doctor/appointments', [
            ...$payload,
            '_preview' => '0',
            'confirmation_token' => $preview['confirmation_token'],
        ])->assertOk();

        Appointment::create([
            'pet_id' => $pet->id,
            'doctor_id' => $otherDoctor->id,
            'scheduled_at' => now()->addDays(2),
            'reason' => 'Otra cita',
        ]);

        $this->actingAs($doctor->user)->get('/doctor/appointments')
            ->assertOk()
            ->assertSee('Consulta preventiva')
            ->assertDontSee('Otra cita')
            ->assertSee($owner->user->username)
            ->assertSee('Buscar propietario')
            ->assertSee('Se asigna automáticamente a tu cuenta.')
            ->assertSee('data-autocomplete-input', false)
            ->assertSee('data-autocomplete-options', false)
            ->assertDontSee('data-select-search', false);

        $tamperedPreview = $this->actingAs($doctor->user)->postJson('/doctor/appointments', [
            ...$payload,
            'doctor_id' => $otherDoctor->id,
            'reason' => 'Cita con asignación automática',
            '_preview' => '1',
        ])->assertOk()->json();
        $this->actingAs($doctor->user)->postJson('/doctor/appointments', [
            ...$payload,
            'doctor_id' => $otherDoctor->id,
            'reason' => 'Cita con asignación automática',
            '_preview' => '0',
            'confirmation_token' => $tamperedPreview['confirmation_token'],
        ])->assertOk();
        $this->assertDatabaseHas('appointments', [
            'reason' => 'Cita con asignación automática',
            'doctor_id' => $doctor->id,
        ]);

        $admin = User::factory()->create(['role' => 'ADMIN']);
        $adminPreview = $this->actingAs($admin)->postJson('/admin/appointments', [
            ...$payload,
            'doctor_id' => $otherDoctor->id,
            'reason' => 'Cita administrativa',
            '_preview' => '1',
        ])->assertOk()->json();
        $this->actingAs($admin)->postJson('/admin/appointments', [
            ...$payload,
            'doctor_id' => $otherDoctor->id,
            'reason' => 'Cita administrativa',
            '_preview' => '0',
            'confirmation_token' => $adminPreview['confirmation_token'],
        ])->assertOk();
    }

    public function test_owner_sees_the_assigned_doctor_and_doctor_can_update_status(): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner, 'Luna');
        $doctor = $this->doctor();
        $appointment = Appointment::create([
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDay(),
            'reason' => 'Control general',
        ]);

        $this->actingAs($owner->user)->get('/owner/appointments')
            ->assertOk()
            ->assertSee($doctor->user->name)
            ->assertSee('Control general');

        $this->actingAs($doctor->user)->patch('/doctor/appointments/'.$appointment->id.'/status', [
            'status' => Appointment::STATUS_COMPLETED,
        ])->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_COMPLETED,
        ]);
    }

    public function test_doctor_can_record_a_consultation_with_clinical_details(): void
    {
        $owner = $this->owner();
        $pet = $this->pet($owner, 'Luna');
        $doctor = $this->doctor();
        $payload = [
            'pet_id' => $pet->id,
            'consulted_at' => now()->subMinutes(5)->format('Y-m-d\TH:i'),
            'reason' => 'Control preventivo',
            'diagnosis' => 'Paciente saludable',
            'treatment' => 'Continuar alimentación habitual',
            'weight' => '12.50',
            'temperature' => '38.5',
            'observations' => 'Revisión sin hallazgos.',
            '_preview' => '1',
        ];

        $preview = $this->actingAs($doctor->user)->postJson('/doctor/consultations', $payload)
            ->assertOk()->json();

        $this->actingAs($doctor->user)->postJson('/doctor/consultations', [
            ...$payload,
            '_preview' => '0',
            'confirmation_token' => $preview['confirmation_token'],
        ])->assertOk();

        $this->assertDatabaseHas('consultations', [
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'reason' => 'Control preventivo',
            'diagnosis' => 'Paciente saludable',
        ]);

        $this->actingAs($doctor->user)->get('/doctor/consultations')
            ->assertOk()
            ->assertSee($owner->full_name)
            ->assertSee('Paciente saludable');
    }

    public function test_owner_can_create_update_and_delete_its_pet(): void
    {
        $owner = $this->owner();
        $species = Species::create(['name' => 'Felino']);
        $breed = Breed::create(['species_id' => $species->id, 'name' => 'Siamés']);
        $payload = [
            'name' => 'Nala',
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'sex' => Pet::SEX_HEMBRA,
            'age_years' => '3',
            'weight' => '4.20',
            'color' => 'Blanco',
            'observations' => 'Tranquila',
            '_preview' => '1',
        ];

        $preview = $this->actingAs($owner->user)->postJson('/owner/pets', $payload)->assertOk()->json();
        $this->actingAs($owner->user)->postJson('/owner/pets', [
            ...$payload,
            '_preview' => '0',
            'confirmation_token' => $preview['confirmation_token'],
        ])->assertOk();
        $pet = Pet::where('name', 'Nala')->firstOrFail();

        $update = $this->actingAs($owner->user)->patchJson('/owner/pets/'.$pet->id, [
            ...$payload,
            'name' => 'Nala Actualizada',
            '_preview' => '1',
            '_version' => RecordVersion::of($pet),
        ])->assertOk()->json();
        $this->actingAs($owner->user)->patchJson('/owner/pets/'.$pet->id, [
            ...$payload,
            'name' => 'Nala Actualizada',
            '_preview' => '0',
            '_version' => RecordVersion::of($pet),
            'confirmation_token' => $update['confirmation_token'],
        ])->assertOk();

        $pet->refresh();
        $delete = $this->actingAs($owner->user)->deleteJson('/owner/pets/'.$pet->id, [
            '_preview' => '1',
            '_version' => RecordVersion::of($pet),
        ])->assertOk()->json();
        $this->actingAs($owner->user)->deleteJson('/owner/pets/'.$pet->id, [
            '_preview' => '0',
            '_version' => RecordVersion::of($pet),
            'confirmation_token' => $delete['confirmation_token'],
        ])->assertOk();

        $this->assertDatabaseMissing('pets', ['id' => $pet->id]);
    }

    public function test_admin_dashboard_shows_worker_application_summary_without_personal_data(): void
    {
        $application = WorkerApplication::factory()->create(['first_name' => 'Persona privada', 'status' => 'PENDING']);

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get('/admin/dashboard');

        $response->assertOk()
            ->assertSee('Solicitudes de trabajadores')
            ->assertSee('Pendientes')
            ->assertSee('1')
            ->assertSee($application->first_name)
            ->assertSee('Historial de solicitudes')
            ->assertDontSee('Ver solicitudes');
    }

    public function test_admin_role_always_sees_worker_approval_block_on_dashboard(): void
    {
        WorkerApplication::factory()->create([
            'first_name' => 'Solicitud',
            'last_name' => 'Pendiente',
            'status' => 'PENDING',
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'profile_id' => null]);
        $rejected = WorkerApplication::factory()->create(['status' => 'REJECTED']);
        AuditLog::create([
            'action' => 'WORKER_APPLICATION_REJECTED',
            'subject_type' => WorkerApplication::class,
            'subject_id' => $rejected->id,
            'before_data' => ['status' => 'PENDING'],
            'after_data' => $rejected->only(['status', 'requested_profile_id', 'assigned_profile_id', 'user_id', 'review_notes']),
        ]);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Solicitudes de trabajadores')
            ->assertSee('Aprobar y activar cuenta')
            ->assertSee('Cambiar estado')
            ->assertSee('Historial de solicitudes')
            ->assertSee('data-datatable', false)
            ->assertSee('Reaceptar');
    }

    public function test_pending_list_escapes_names_and_excludes_reviewed_profiles(): void
    {
        $doctor = $this->doctor();
        $doctor->approval_status = 'PENDING';
        $doctor->save();
        $doctor->user->name = '<script>alert(1)</script>';
        $doctor->user->save();
        $approved = $this->doctor();
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))->get('/admin/doctors/pending')
            ->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($approved->user->email);
    }

    public function test_navigation_links_all_resolve_for_each_role(): void
    {
        $users = [User::factory()->create(['role' => 'ADMIN']), $this->doctor()->user, User::factory()->create()];
        foreach ($users as $user) {
            $response = $this->actingAs($user)->get('/'.strtolower($user->role).'/dashboard')->assertOk();
            preg_match_all('/<a href="([^"]+)" class="sidebar-link/', $response->getContent(), $matches);
            $this->assertNotEmpty($matches[1]);
            foreach ($matches[1] as $url) {
                $this->get($url)->assertOk();
            }
        }
    }

    public function test_admin_navigation_exposes_clinic_configuration(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'ADMIN']))->get('/admin/dashboard');

        $response
            ->assertOk()
            ->assertSee('Configuración de clínica')
            ->assertSee(route('admin.settings'), false);
    }

    public function test_doctor_does_not_see_or_open_clinic_configuration(): void
    {
        $doctor = $this->doctor()->user;

        $this->actingAs($doctor)->get('/doctor/dashboard')
            ->assertOk()
            ->assertDontSee('Configuración de clínica')
            ->assertDontSee(route('admin.settings'), false);

        $this->actingAs($doctor)->get('/admin/settings')->assertForbidden();
    }
}
