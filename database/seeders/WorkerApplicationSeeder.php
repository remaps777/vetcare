<?php

namespace Database\Seeders;

use App\Models\WorkerApplication;
use Illuminate\Database\Seeder;

class WorkerApplicationSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            WorkerApplication::factory()->count(3)->create();
        }
    }
}
