<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_fillable_and_hidden_attributes(): void
    {
        $user = new User([
            'name' => 'Dr. Carlos',
            'username' => 'carlosvet',
            'email' => 'carlos@vetcare.local',
            'password' => 'Veterinaria2026!',
            'role' => User::ROLE_DOCTOR, // Intent de mass-assignment
        ]);

        $this->assertEquals('Dr. Carlos', $user->name);
        $this->assertEquals('carlosvet', $user->username);
        $this->assertEquals('carlos@vetcare.local', $user->email);
        // role se excluyó de fillable por seguridad; no se asigna vía mass assignment y permanece con el default OWNER
        $this->assertEquals(User::ROLE_OWNER, $user->role);
        $this->assertNotEquals(User::ROLE_DOCTOR, $user->role);

        $array = $user->toArray();
        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_password_is_automatically_hashed(): void
    {
        $user = new User([
            'password' => 'PlainPassword123!',
        ]);

        $this->assertTrue(Hash::check('PlainPassword123!', $user->password));
    }

    public function test_user_database_defaults(): void
    {
        $user = User::create([
            'name' => 'Dueño Principal',
            'username' => 'propietario1',
            'email' => 'dueno@vetcare.local',
            'password' => 'Dueno123!',
        ]);

        $user->refresh();

        $this->assertEquals('OWNER', $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_username_must_be_unique(): void
    {
        User::create([
            'name' => 'Usuario Uno',
            'username' => 'vetadmin',
            'email' => 'uno@vetcare.local',
            'password' => 'Pass123!',
        ]);

        $this->expectException(QueryException::class);

        User::create([
            'name' => 'Usuario Dos',
            'username' => 'vetadmin', // Duplicate username
            'email' => 'dos@vetcare.local',
            'password' => 'Pass123!',
        ]);
    }

    public function test_email_must_be_unique(): void
    {
        User::create([
            'name' => 'Usuario Uno',
            'username' => 'user_one',
            'email' => 'same@vetcare.local',
            'password' => 'Pass123!',
        ]);

        $this->expectException(QueryException::class);

        User::create([
            'name' => 'Usuario Dos',
            'username' => 'user_two',
            'email' => 'same@vetcare.local', // Duplicate email
            'password' => 'Pass123!',
        ]);
    }

    public function test_user_roles_constants(): void
    {
        $this->assertContains('ADMIN', User::ROLES);
        $this->assertContains('DOCTOR', User::ROLES);
        $this->assertContains('OWNER', User::ROLES);
        $this->assertNotContains('RECEPCIONISTA', User::ROLES);
    }
}
