<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public static function credentials(): array
    {
        return ['admin username' => ['ADMIN', 'username'], 'admin email' => ['ADMIN', 'email'], 'owner username' => ['OWNER', 'username'], 'owner email' => ['OWNER', 'email']];
    }

    #[DataProvider('credentials')]
    public function test_active_user_can_login_by_username_or_email(string $role, string $field): void
    {
        $user = User::factory()->create(['role' => $role]);
        $passwordHash = $user->password;

        $this->post('/login', ['login' => $user->$field, 'password' => 'password'])
            ->assertRedirect('/'.strtolower($role).'/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame($passwordHash, $user->fresh()->password);
    }

    public function test_wrong_password_does_not_authenticate_or_flash_password(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['login' => $user->username, 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors(['login' => 'Las credenciales no son correctas.'])
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public static function deniedAccounts(): array
    {
        return [
            'pending' => ['DOCTOR', false, 'PENDING', 'pendiente'],
            'pending active' => ['DOCTOR', true, 'PENDING', 'pendiente'],
            'rejected' => ['DOCTOR', false, 'REJECTED', 'rechazada'],
            'approved inactive' => ['DOCTOR', false, 'APPROVED', 'inactiva'],
            'missing profile' => ['DOCTOR', true, null, 'no está autorizado'],
            'inactive admin' => ['ADMIN', false, null, 'inactiva'],
            'inactive owner' => ['OWNER', false, null, 'inactiva'],
            'unknown role' => ['RECEPCIONISTA', true, null, 'rol autorizado'],
        ];
    }

    #[DataProvider('deniedAccounts')]
    public function test_account_must_be_active_and_doctor_approved(string $role, bool $active, ?string $status, string $message): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => $active]);
        if ($status !== null) {
            $profile = $user->doctorProfile()->create(['dni' => '12345678', 'license_number' => 'CMVP-01']);
            $profile->approval_status = $status;
            $profile->save();
        }

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertStringContainsString($message, session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_approved_doctor_can_login(): void
    {
        $user = User::factory()->create(['role' => 'DOCTOR']);
        $profile = $user->doctorProfile()->create(['dni' => '12345678', 'license_number' => 'CMVP-01']);
        $profile->approve();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])->assertRedirect('/doctor/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['login' => 'missing', 'password' => 'wrong']);
        }

        $this->post('/login', ['login' => 'missing', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('login'));
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private' => 'value'])->post('/logout')
            ->assertRedirect('/login')->assertSessionMissing('private');
        $this->assertGuest();
        $this->get('/owner/dashboard')->assertRedirect('/login');
    }

    public function test_existing_session_loses_access_when_account_is_disabled(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/owner/dashboard')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_inactive_api_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/user')->assertForbidden();
    }
}
