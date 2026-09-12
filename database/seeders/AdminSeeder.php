<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_INITIAL_EMAIL', 'admin@vetcare.local');
        $adminUsername = env('ADMIN_INITIAL_USERNAME', 'admin');
        $adminName = env('ADMIN_INITIAL_NAME', 'Administrador Inicial');

        // No se coloca una contraseña fija en el código.
        // Se toma de la variable de entorno ADMIN_INITIAL_PASSWORD o se solicita/genera dinámicamente.
        $password = env('ADMIN_INITIAL_PASSWORD');

        if (empty($password)) {
            if ($this->command && $this->command->getOutput()->isDecorated()) {
                $password = $this->command->secret('Ingrese la contraseña para el usuario administrador:');
            }

            if (empty($password)) {
                $password = Str::random(16);
                if ($this->command) {
                    $this->command->warn('No se especificó ADMIN_INITIAL_PASSWORD en .env.');
                    $this->command->info("Contraseña temporal generada para el admin: {$password}");
                }
            }
        }

        $admin = User::updateOrCreate(
            ['username' => $adminUsername],
            [
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]
        );

        if ($this->command) {
            $this->command->info("Usuario administrador '{$admin->username}' ({$admin->email}) listo.");
        }
    }
}
