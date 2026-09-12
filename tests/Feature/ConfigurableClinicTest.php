<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurableClinicTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_species_only_after_server_preview_and_confirmation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = [
            'name' => 'Felino',
            'is_active' => '1',
            '_preview' => '1',
        ];

        $preview = $this->actingAs($admin)->postJson('/admin/species', $payload)->assertOk()->json();

        $this->assertArrayHasKey('confirmation_token', $preview);
        $this->assertDatabaseMissing('species', ['name' => 'Felino']);

        $this->actingAs($admin)->postJson('/admin/species', [
            ...$payload,
            '_preview' => '0',
            'confirmation_token' => $preview['confirmation_token'],
        ])->assertOk();

        $this->assertDatabaseHas('species', ['name' => 'Felino', 'is_active' => true]);
        $this->assertSame(1, AuditLog::where('subject_type', 'species')->count());
    }

    public function test_owner_cannot_change_species(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->postJson('/admin/species', [
            'name' => 'No autorizado',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('species', ['name' => 'No autorizado']);
    }

    public function test_doctor_can_configure_a_breed_for_an_active_species(): void
    {
        $doctor = User::factory()->create(['role' => User::ROLE_DOCTOR]);
        $doctor->doctorProfile()->create([
            'dni' => '12345678',
            'license_number' => 'CMVP-001',
        ])->approve();
        $species = Species::create(['name' => 'Canino', 'is_active' => true]);
        $payload = [
            'name' => 'Mestizo',
            'species_id' => $species->id,
            'is_active' => '1',
            'current_password' => 'password',
            '_preview' => '1',
        ];

        $preview = $this->actingAs($doctor)->postJson('/doctor/breeds', $payload)
            ->assertOk()->json();

        $this->actingAs($doctor)->postJson('/doctor/breeds', [
            ...$payload,
            '_preview' => '0',
            'confirmation_token' => $preview['confirmation_token'],
        ])->assertOk();

        $this->assertDatabaseHas('breeds', ['name' => 'Mestizo', 'species_id' => $species->id]);
    }
}
