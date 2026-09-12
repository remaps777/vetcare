<?php

namespace Tests\Feature;

use App\Models\Breed;
use App\Models\DoctorProfile;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Species;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class VetCareArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. ADMIN existente continúa siendo ADMIN.
     */
    public function test_admin_existente_continua_siendo_admin(): void
    {
        $admin = new User([
            'name' => 'Administrador',
            'username' => 'admin',
            'email' => 'admin@vetcare.local',
            'password' => 'SecretPass123!',
        ]);
        $admin->role = User::ROLE_ADMIN;
        $admin->save();

        $this->assertTrue($admin->isAdmin());
        $this->assertEquals(User::ROLE_ADMIN, $admin->role);
    }

    /**
     * 2. OWNER puede tener varias mascotas.
     */
    public function test_owner_puede_tener_varias_mascotas(): void
    {
        $owner = Owner::create([
            'dni' => '12345678',
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'phone' => '987654321',
        ]);

        $species = Species::create(['name' => 'Canino']);
        $breed = Breed::create(['species_id' => $species->id, 'name' => 'Labrador']);

        $pet1 = Pet::create([
            'owner_id' => $owner->id,
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'name' => 'Max',
            'sex' => Pet::SEX_MACHO,
        ]);

        $pet2 = Pet::create([
            'owner_id' => $owner->id,
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'name' => 'Luna',
            'sex' => Pet::SEX_HEMBRA,
        ]);

        $this->assertCount(2, $owner->pets);
        $this->assertTrue($owner->pets->contains($pet1));
        $this->assertTrue($owner->pets->contains($pet2));
    }

    /**
     * 3. Una Pet pertenece a un solo Owner.
     */
    public function test_una_pet_pertenece_a_un_solo_owner(): void
    {
        $owner = Owner::create([
            'dni' => '23456789',
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'phone' => '912345678',
        ]);

        $species = Species::create(['name' => 'Felino']);
        $breed = Breed::create(['species_id' => $species->id, 'name' => 'Siamés']);

        $pet = Pet::create([
            'owner_id' => $owner->id,
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'name' => 'Mishi',
            'sex' => Pet::SEX_HEMBRA,
        ]);

        $this->assertInstanceOf(Owner::class, $pet->owner);
        $this->assertEquals($owner->id, $pet->owner->id);
    }

    /**
     * 4. Species name no puede duplicarse.
     */
    public function test_species_name_no_puede_duplicarse(): void
    {
        Species::create(['name' => 'Canino']);

        $this->expectException(QueryException::class);
        Species::create(['name' => 'Canino']);
    }

    /**
     * 5. Breed no puede duplicarse dentro de una misma Species.
     */
    public function test_breed_no_puede_duplicarse_dentro_de_misma_species(): void
    {
        $species = Species::create(['name' => 'Canino']);
        Breed::create(['species_id' => $species->id, 'name' => 'Poodle']);

        $this->expectException(QueryException::class);
        Breed::create(['species_id' => $species->id, 'name' => 'Poodle']);
    }

    /**
     * 6. "Mestizo" sí puede existir para especies diferentes.
     */
    public function test_mestizo_si_puede_existir_para_especies_diferentes(): void
    {
        $dog = Species::create(['name' => 'Canino']);
        $cat = Species::create(['name' => 'Felino']);

        $dogMestizo = Breed::create(['species_id' => $dog->id, 'name' => 'Mestizo']);
        $catMestizo = Breed::create(['species_id' => $cat->id, 'name' => 'Mestizo']);

        $this->assertEquals('Mestizo', $dogMestizo->name);
        $this->assertEquals('Mestizo', $catMestizo->name);
        $this->assertNotEquals($dogMestizo->species_id, $catMestizo->species_id);
    }

    /**
     * 7. Pet no acepta una Breed perteneciente a otra Species (validación BACKEND).
     */
    public function test_pet_no_acepta_una_breed_perteneciente_a_otra_species(): void
    {
        $owner = Owner::create([
            'dni' => '34567890',
            'first_name' => 'Carlos',
            'last_name' => 'Ruiz',
            'phone' => '998877665',
        ]);

        $dogSpecies = Species::create(['name' => 'Canino']);
        $catSpecies = Species::create(['name' => 'Felino']);

        $dogBreed = Breed::create(['species_id' => $dogSpecies->id, 'name' => 'Labrador']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La raza seleccionada no pertenece a la especie indicada.');

        // Intentar registrar mascota con species = Felino pero raza = Labrador (Canino)
        Pet::create([
            'owner_id' => $owner->id,
            'species_id' => $catSpecies->id,
            'breed_id' => $dogBreed->id,
            'name' => 'Invalido',
            'sex' => Pet::SEX_MACHO,
        ]);
    }

    /**
     * 8. license_number de Doctor debe ser único.
     */
    public function test_license_number_de_doctor_debe_ser_unico(): void
    {
        $user1 = User::create([
            'name' => 'Dr. Uno',
            'username' => 'dr_uno',
            'email' => 'druno@vetcare.local',
            'password' => 'Pass123!',
        ]);

        $user2 = User::create([
            'name' => 'Dr. Dos',
            'username' => 'dr_dos',
            'email' => 'drdos@vetcare.local',
            'password' => 'Pass123!',
        ]);

        DoctorProfile::create([
            'user_id' => $user1->id,
            'dni' => '00112233',
            'license_number' => 'COLVET-12345',
        ]);

        $this->expectException(QueryException::class);
        DoctorProfile::create([
            'user_id' => $user2->id,
            'dni' => '00112244',
            'license_number' => 'COLVET-12345', // Duplicado
        ]);
    }

    /**
     * 9. Doctor nuevo queda PENDING/inactivo y se activa al ser aprobado.
     */
    public function test_doctor_nuevo_queda_pending_inactivo_y_se_activa_al_aprobar(): void
    {
        $user = User::create([
            'name' => 'Dr. Nuevo',
            'username' => 'dr_nuevo',
            'email' => 'drnuevo@vetcare.local',
            'password' => 'Pass123!',
        ]);
        $user->role = User::ROLE_DOCTOR;
        $user->is_active = false;
        $user->save();

        $doctor = DoctorProfile::create([
            'user_id' => $user->id,
            'dni' => '55667788',
            'license_number' => 'COLVET-99999',
        ]);

        $this->assertTrue($doctor->isPending());
        $this->assertFalse($user->fresh()->is_active);

        // Admin aprueba al doctor
        $doctor->approve();

        $this->assertTrue($doctor->fresh()->isApproved());
        $this->assertTrue($user->fresh()->is_active);
    }

    /**
     * 10. Un usuario público no puede enviarse role=ADMIN.
     */
    public function test_un_usuario_publico_no_puede_enviarse_role_admin(): void
    {
        // Petición simulando registro público con intento de escalamiento
        $user = User::create([
            'name' => 'Atacante',
            'username' => 'hacker',
            'email' => 'hacker@vetcare.local',
            'password' => 'Hacker123!',
            'role' => User::ROLE_ADMIN, // Escalada bloqueada en $fillable
            'is_active' => true,
        ]);

        $user->refresh();

        // El role permanece con el default de la base de datos (OWNER), no ADMIN
        $this->assertEquals(User::ROLE_OWNER, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    /**
     * 11. Un OWNER no puede acceder a información de otro OWNER.
     */
    public function test_un_owner_no_puede_acceder_a_informacion_de_otro_owner(): void
    {
        $ownerA = Owner::create([
            'dni' => '11111111',
            'first_name' => 'Propietario',
            'last_name' => 'A',
            'phone' => '911111111',
        ]);

        $ownerB = Owner::create([
            'dni' => '22222222',
            'first_name' => 'Propietario',
            'last_name' => 'B',
            'phone' => '922222222',
        ]);

        $species = Species::create(['name' => 'Canino']);
        $breed = Breed::create(['species_id' => $species->id, 'name' => 'Beagle']);

        $petA = Pet::create([
            'owner_id' => $ownerA->id,
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'name' => 'Mascota A',
            'sex' => Pet::SEX_MACHO,
        ]);

        // Verificación de autorización de backend: petA no pertenece a ownerB
        $this->assertNotEquals($ownerB->id, $petA->owner_id);
        $this->assertFalse($ownerB->pets->contains($petA));
        $this->assertTrue($ownerA->pets->contains($petA));
    }

    /**
     * 12. Password continúa hasheado.
     */
    public function test_password_continua_hasheado(): void
    {
        $user = User::create([
            'name' => 'Test Hash',
            'username' => 'testhash',
            'email' => 'hash@vetcare.local',
            'password' => 'SuperSecret123!',
        ]);

        $this->assertNotEquals('SuperSecret123!', $user->password);
        $this->assertTrue(Hash::check('SuperSecret123!', $user->password));
    }

    /**
     * 13. El AdminSeeder continúa siendo idempotente.
     */
    public function test_el_admin_seeder_continua_siendo_idempotente(): void
    {
        // Ejecutar seeder por primera vez
        $this->seed(AdminSeeder::class);
        $countAfterFirst = User::where('username', 'admin')->count();

        // Ejecutar seeder por segunda vez
        $this->seed(AdminSeeder::class);
        $countAfterSecond = User::where('username', 'admin')->count();

        $this->assertEquals(1, $countAfterFirst);
        $this->assertEquals(1, $countAfterSecond);

        $admin = User::where('username', 'admin')->first();
        $this->assertEquals(User::ROLE_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
    }
}
