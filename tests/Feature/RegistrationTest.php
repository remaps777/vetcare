<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Specialty;
use App\Models\User;
use App\Models\WorkerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $specialty = Specialty::firstOrCreate(['name' => 'Medicina general veterinaria'], ['is_active' => true]);

        return ['first_name' => 'María', 'last_name' => 'Pérez', 'dni' => '12345678', 'phone' => '987654321', 'email' => 'maria@example.test', 'username' => 'maria', 'password' => 'SecurePass123!', 'password_confirmation' => 'SecurePass123!', 'license_number' => '12345', 'specialty_id' => $specialty->id];
    }

    public function test_owner_registration_ignores_privileged_fields(): void
    {
        $this->post('/register/owner', [...$this->payload(), 'role' => 'ADMIN', 'is_active' => false, 'approval_status' => 'APPROVED', 'user_id' => 999])
            ->assertRedirect('/login')->assertSessionHasNoErrors();

        $user = User::where('username', 'maria')->firstOrFail();
        $this->assertSame('OWNER', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertDatabaseHas('owners', ['user_id' => $user->id, 'first_name' => 'María', 'last_name' => 'Pérez', 'is_active' => true]);
        $this->assertDatabaseCount('doctor_profiles', 0);
        $this->assertGuest();
    }

    public function test_legacy_doctor_registration_creates_a_pending_application_without_a_user(): void
    {
        $this->post('/register/doctor', [...$this->payload(), 'role' => 'ADMIN', 'is_active' => true, 'approval_status' => 'APPROVED'])
            ->assertRedirect('/login')->assertSessionHasNoErrors()->assertSessionHas('warning');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('doctor_profiles', 0);
        $this->assertDatabaseHas('worker_applications', ['username' => 'maria', 'status' => 'PENDING', 'license_number' => '12345']);
        $this->assertDatabaseCount('owners', 0);
        $this->assertGuest();
    }

    public static function invalidInputs(): array
    {
        return [
            'dni' => ['dni', '123', 'El DNI debe tener 8 dígitos.'],
            'phone' => ['phone', 'abc123', 'El teléfono solo puede contener números.'],
            'license_number' => ['license_number', 'CMVP-123', 'La colegiatura solo puede contener números.'],
            'username' => ['username', 'bad@email', 'El usuario solo admite letras, números, puntos, guiones y guiones bajos.'],
            'email' => ['email', 'bad', 'Ingresa un correo electrónico válido.'],
            'confirmation' => ['password_confirmation', 'different', 'Las contraseñas no coinciden.'],
            'missing specialty' => ['specialty_id', '', 'Selecciona una especialidad.'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_registration_creates_no_records(string $field, string $value, string $message): void
    {
        $this->post('/register/doctor', [...$this->payload(), $field => $value])->assertSessionHasErrors();
        $this->assertContains($message, session('errors')->all());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('doctor_profiles', 0);
    }

    public function test_duplicate_identity_is_rejected(): void
    {
        User::factory()->create(['username' => 'maria', 'email' => 'maria@example.test']);
        $this->post('/register/owner', $this->payload())->assertSessionHasErrors(['username', 'email']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('owners', 0);
    }

    public static function profileTypes(): array
    {
        return ['owner' => ['owner', Owner::class], 'doctor' => ['doctor', WorkerApplication::class]];
    }

    #[DataProvider('profileTypes')]
    public function test_registration_rolls_back_user_when_profile_cannot_be_saved(string $type, string $model): void
    {
        $model::creating(function (): void {
            throw new \RuntimeException('Simulated profile storage failure');
        });
        try {
            $this->post('/register/'.$type, $this->payload())->assertServerError();
            $this->assertDatabaseCount('users', 0);
        } finally {
            $model::flushEventListeners();
        }
    }
}
