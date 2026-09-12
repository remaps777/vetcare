<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profileId = DB::table('profiles')->where('code', 'DOCTOR')->value('id');
        DB::table('doctor_profiles')->whereIn('approval_status', ['PENDING', 'REJECTED'])->orderBy('id')->chunkById(100, function ($doctors) use ($profileId): void {
            foreach ($doctors as $doctor) {
                $user = DB::table('users')->find($doctor->user_id);
                if (! $user || $user->is_active || DB::table('worker_applications')->where('user_id', $user->id)->exists()) {
                    continue;
                }
                $names = explode(' ', trim($user->name), 2);
                $id = DB::table('worker_applications')->insertGetId([
                    'first_name' => mb_substr($names[0], 0, 100), 'last_name' => mb_substr($names[1] ?? '', 0, 100),
                    'dni' => $doctor->dni, 'phone' => $doctor->phone, 'email' => $user->email, 'username' => $user->username,
                    'password' => null, 'requested_profile_id' => $profileId, 'specialty_id' => $doctor->specialty_id,
                    'license_number' => $doctor->license_number, 'status' => $doctor->approval_status, 'user_id' => $user->id,
                    'review_notes' => 'Solicitud anterior incorporada. Verifica los datos personales y profesionales antes de aprobar.',
                    'created_at' => $doctor->created_at, 'updated_at' => now(),
                ]);
                DB::table('audit_logs')->insert([
                    'user_id' => null, 'action' => 'WORKER_APPLICATION_IMPORTED', 'subject_type' => 'App\\Models\\WorkerApplication', 'subject_id' => $id,
                    'before_data' => null, 'after_data' => json_encode(['status' => $doctor->approval_status, 'requested_profile_id' => $profileId, 'user_id' => $user->id]),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Preserve imported applications and their review history.
    }
};
