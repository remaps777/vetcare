<?php

namespace Tests\Feature;

use App\Models\Breed;
use App\Models\Pet;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPetsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_only_their_active_pets_with_bearer_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $owner = $user->owner()->create(['dni' => '12345678', 'first_name' => 'Ana', 'last_name' => 'Owner', 'phone' => '999111222']);
        $otherOwner = User::factory()->create()->owner()->create(['dni' => '87654321', 'first_name' => 'Otro', 'last_name' => 'Owner', 'phone' => '999111333']);
        $species = Species::create(['name' => 'Canino']);
        $breed = Breed::create(['species_id' => $species->id, 'name' => 'Mestizo']);
        $visiblePet = Pet::create(['owner_id' => $owner->id, 'species_id' => $species->id, 'breed_id' => $breed->id, 'name' => 'Luna', 'sex' => Pet::SEX_HEMBRA]);
        Pet::create(['owner_id' => $owner->id, 'species_id' => $species->id, 'breed_id' => $breed->id, 'name' => 'Inactive', 'sex' => Pet::SEX_MACHO, 'is_active' => false]);
        Pet::create(['owner_id' => $otherOwner->id, 'species_id' => $species->id, 'breed_id' => $breed->id, 'name' => 'Other', 'sex' => Pet::SEX_MACHO]);
        $token = $user->createToken('postman')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/owner/pets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $visiblePet->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_token_endpoint_returns_a_bearer_token_for_an_active_owner(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $user->owner()->create(['dni' => '12345678', 'first_name' => 'Ana', 'last_name' => 'Owner', 'phone' => '999111222']);

        $this->postJson('/api/auth/token', [
            'login' => $user->username,
            'password' => 'password',
            'device_name' => 'Postman',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'username', 'name', 'role']])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_non_owner_cannot_use_owner_pets_api(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $token = $user->createToken('postman')->plainTextToken;

        $this->withToken($token)->getJson('/api/owner/pets')->assertForbidden();
    }

    public function test_owner_can_create_update_and_delete_a_pet_through_the_api(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $owner = $user->owner()->create(['dni' => '12345678', 'first_name' => 'Ana', 'last_name' => 'Owner', 'phone' => '999111222']);
        $species = Species::create(['name' => 'Canino']);
        $breed = Breed::create(['species_id' => $species->id, 'name' => 'Mestizo']);
        $token = $user->createToken('postman')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/owner/pets', [
            'name' => 'Luna',
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'sex' => Pet::SEX_HEMBRA,
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'Luna');
        $petId = $response->json('data.id');
        $this->assertDatabaseHas('pets', ['id' => $petId, 'owner_id' => $owner->id]);

        $this->withToken($token)->putJson('/api/owner/pets/'.$petId, [
            'name' => 'Luna Actualizada',
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'sex' => Pet::SEX_HEMBRA,
        ])->assertOk()->assertJsonPath('data.name', 'Luna Actualizada');

        $this->withToken($token)->getJson('/api/owner/pets/'.$petId)
            ->assertOk()
            ->assertJsonPath('data.name', 'Luna Actualizada');

        $this->withToken($token)->deleteJson('/api/owner/pets/'.$petId)
            ->assertOk()
            ->assertJsonPath('message', 'Mascota eliminada correctamente.');
        $this->assertDatabaseMissing('pets', ['id' => $petId]);
    }
}
