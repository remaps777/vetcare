<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkerApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(),
            'dni' => fake()->unique()->numerify('########'), 'phone' => '987654321',
            'email' => fake()->unique()->safeEmail(), 'username' => fake()->unique()->userName(),
            'password' => 'SecurePass123!', 'requested_profile_id' => fn () => Profile::where('code', 'CAJA')->firstOrFail()->id,
        ];
    }
}
