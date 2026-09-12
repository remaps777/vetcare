<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DoctorApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function doctor(): DoctorProfile
    {
        return User::factory()->create(['role' => 'DOCTOR', 'is_active' => false])
            ->doctorProfile()->create(['dni' => '12345678', 'license_number' => 'CMVP-01']);
    }

    public static function decisions(): array
    {
        return ['approve' => ['approve', 'APPROVED', true], 'reject' => ['reject', 'REJECTED', false]];
    }

    #[DataProvider('decisions')]
    public function test_admin_can_decide_pending_doctor(string $action, string $status, bool $active): void
    {
        $doctor = $this->doctor();
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)->patch('/admin/doctors/'.$doctor->id.'/'.$action)->assertRedirect('/admin/doctors/pending');

        $this->assertSame($status, $doctor->fresh()->approval_status);
        $this->assertSame($active, $doctor->user->fresh()->is_active);
    }

    #[DataProvider('decisions')]
    public function test_owner_cannot_decide_doctor(string $action, string $status, bool $active): void
    {
        $doctor = $this->doctor();
        $this->actingAs(User::factory()->create())->patch('/admin/doctors/'.$doctor->id.'/'.$action)->assertForbidden();
        $this->assertTrue($doctor->fresh()->isPending());
        $this->assertFalse($doctor->user->is_active);
    }

    public function test_doctor_cannot_approve_himself(): void
    {
        $doctor = $this->doctor();
        $this->actingAs($doctor->user)->patch('/admin/doctors/'.$doctor->id.'/approve')->assertRedirect('/login');
        $this->assertTrue($doctor->fresh()->isPending());
        $this->assertFalse($doctor->user->fresh()->is_active);
    }

    public function test_approved_doctor_cannot_approve_another_doctor(): void
    {
        $doctor = $this->doctor();
        $actor = User::factory()->create(['role' => 'DOCTOR']);
        $profile = $actor->doctorProfile()->create(['dni' => '87654321', 'license_number' => 'CMVP-02']);
        $profile->approve();

        $this->actingAs($actor)->patch('/admin/doctors/'.$doctor->id.'/approve')->assertForbidden();
        $this->assertTrue($doctor->fresh()->isPending());
    }

    public function test_already_reviewed_request_cannot_be_overwritten(): void
    {
        $doctor = $this->doctor();
        $doctor->approve();
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->patch('/admin/doctors/'.$doctor->id.'/reject')->assertConflict();
        $this->assertTrue($doctor->fresh()->isApproved());
        $this->assertTrue($doctor->user->fresh()->is_active);
    }
}
