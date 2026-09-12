<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_and_update_their_profile_data(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user->owner()->create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'dni' => '12345678',
            'phone' => '999111222',
            'email' => $user->email,
            'address' => 'Dirección anterior',
        ]);

        $this->actingAs($user)->get('/owner/profile')
            ->assertOk()
            ->assertSee('Dirección anterior');

        $this->actingAs($user)->patch('/owner/profile', [
            'name' => 'Ana Torres Actualizada',
            'first_name' => 'Ana María',
            'last_name' => 'Torres Actualizada',
            'email' => 'ana.actualizada@example.test',
            'phone' => '999333444',
            'address' => 'Nueva dirección',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Ana María Torres Actualizada', 'phone' => '999333444']);
        $this->assertDatabaseHas('owners', ['user_id' => $user->id, 'first_name' => 'Ana María', 'last_name' => 'Torres Actualizada', 'address' => 'Nueva dirección', 'phone' => '999333444']);
    }

    public function test_doctor_can_view_and_update_contact_and_specialty(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DOCTOR]);
        $doctorProfile = $user->doctorProfile()->create(['dni' => '87654321', 'license_number' => 'CMVP-02']);
        $doctorProfile->approve();

        $this->actingAs($user)->patch('/doctor/profile', [
            'name' => 'Dr. Ana Torres',
            'email' => 'doctora@example.test',
            'phone' => '999555666',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '999555666']);
        $this->assertDatabaseHas('doctor_profiles', ['user_id' => $user->id, 'phone' => '999555666']);
    }

    public function test_doctor_can_search_registered_owners_and_admin_can_see_doctor_contact_data(): void
    {
        $doctor = User::factory()->create(['role' => User::ROLE_DOCTOR, 'phone' => '999888777']);
        $doctorProfile = $doctor->doctorProfile()->create(['dni' => '87654321', 'license_number' => 'CMVP-02']);
        $doctorProfile->approve();
        $owner = User::factory()->create(['name' => 'Cliente Visible', 'role' => User::ROLE_OWNER]);
        $owner->owner()->create(['first_name' => 'Cliente', 'last_name' => 'Visible', 'dni' => '12345678', 'phone' => '999777888', 'email' => $owner->email]);

        $this->actingAs($doctor)->get('/doctor/owners')
            ->assertOk()
            ->assertSee('Cliente Visible')
            ->assertSee('999777888');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee($doctor->name)
            ->assertSee('999888777');
    }

    public function test_guest_is_redirected_to_login_from_profile_pages(): void
    {
        $this->get('/owner/profile')->assertRedirect('/login');
        $this->get('/doctor/profile')->assertRedirect('/login');
    }

    public function test_owner_dashboard_includes_profile_form_without_separate_profile_menu_link(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user->owner()->create([
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'dni' => '12345678',
            'phone' => '999111222',
            'email' => $user->email,
        ]);

        $this->actingAs($user)->get('/owner/dashboard')
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('name="address"', false)
            ->assertDontSee('href="/owner/profile"', false);
    }

    public function test_doctor_dashboard_includes_profile_form_without_separate_profile_menu_link(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_DOCTOR]);
        $doctorProfile = $user->doctorProfile()->create(['dni' => '87654321', 'license_number' => 'CMVP-02']);
        $doctorProfile->approve();

        $this->actingAs($user)->get('/doctor/dashboard')
            ->assertOk()
            ->assertSee('Mi perfil')
            ->assertSee('name="specialty_id"', false)
            ->assertDontSee('href="/doctor/profile"', false);
    }
}
