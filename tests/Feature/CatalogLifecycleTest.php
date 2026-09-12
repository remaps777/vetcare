<?php

namespace Tests\Feature;

use App\Models\Breed;
use App\Models\Profile;
use App\Models\Species;
use App\Models\User;
use App\Services\RecordVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'profile_id' => Profile::where('code', 'ADMINISTRADOR')->value('id'),
        ]);
    }

    public function test_catalog_record_can_be_deactivated_and_reactivated_without_losing_history(): void
    {
        $species = Species::create(['name' => 'Canino', 'is_active' => true]);
        $breed = Breed::create(['name' => 'Mestizo', 'species_id' => $species->id, 'is_active' => true]);
        $this->actingAs($this->admin());

        $this->changeStatus($species, false);

        $this->assertFalse($species->fresh()->is_active);
        $this->assertModelExists($breed);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'species', 'subject_id' => $species->id]);
        $this->get('/admin/breeds')->assertDontSee('<option value="'.$species->id.'">', false);

        $this->changeStatus($species->fresh(), true);

        $this->assertTrue($species->fresh()->is_active);
        $this->assertModelExists($breed);
    }

    public function test_catalog_admin_can_filter_active_and_inactive_records(): void
    {
        Species::create(['name' => 'Activa visible', 'is_active' => true]);
        Species::create(['name' => 'Inactiva visible', 'is_active' => false]);
        $this->actingAs($this->admin());

        $this->get('/admin/species?active=1')->assertSee('Activa visible')->assertDontSee('Inactiva visible');
        $this->get('/admin/species?active=0')->assertSee('Inactiva visible')->assertDontSee('Activa visible');
        $this->get('/admin/species')->assertSee('Activa visible')->assertSee('Inactiva visible');
    }

    public function test_user_without_catalog_permission_cannot_change_status_directly(): void
    {
        $species = Species::create(['name' => 'Protegida', 'is_active' => true]);
        $owner = User::factory()->create(['profile_id' => Profile::where('code', 'PROPIETARIO')->value('id')]);

        $this->actingAs($owner)->patchJson('/admin/catalogs/species/'.$species->id.'/status', [
            'is_active' => false,
            '_version' => RecordVersion::of($species),
            '_preview' => true,
        ])->assertForbidden();

        $this->assertTrue($species->fresh()->is_active);
    }

    private function changeStatus(Species $species, bool $active): void
    {
        $species->refresh();

        $payload = [
            'is_active' => $active,
            '_version' => RecordVersion::of($species),
            '_preview' => true,
        ];
        $preview = $this->patchJson('/admin/catalogs/species/'.$species->id.'/status', $payload)->assertOk()->json();

        $this->patchJson('/admin/catalogs/species/'.$species->id.'/status', [
            ...$payload,
            '_preview' => false,
            'confirmation_token' => $preview['confirmation_token'],
        ])->assertOk();
    }
}
