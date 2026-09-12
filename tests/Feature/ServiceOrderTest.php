<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Breed;
use App\Models\Profile;
use App\Models\ServiceOrder;
use App\Models\Species;
use App\Models\User;
use App\Services\ServiceOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_appointment_creates_one_idempotent_service_order_and_audits_creation(): void
    {
        [$doctor, $appointment] = $this->appointment();

        $this->actingAs($doctor)->post(route('appointments.attend', $appointment))->assertRedirect();

        $order = ServiceOrder::firstOrFail();
        $this->assertSame($appointment->pet->owner_id, $order->owner_id);
        $this->assertSame($appointment->pet_id, $order->pet_id);
        $this->assertSame($appointment->doctor_id, $order->doctor_id);
        $this->assertSame(ServiceOrder::ORIGIN_APPOINTMENT, $order->origin);
        $this->assertSame(ServiceOrder::STATUS_OPEN, $order->status);
        $this->assertSame(ServiceOrder::PAYMENT_PENDING, $order->payment_status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'SERVICE_ORDER_CREATED',
            'subject_type' => ServiceOrder::class,
            'subject_id' => $order->id,
        ]);

        $this->actingAs($doctor)->post(route('appointments.attend', $appointment))->assertRedirect();

        $this->assertDatabaseCount('service_orders', 1);
        $this->assertSame($order->id, ServiceOrder::firstOrFail()->id);
    }

    public function test_pending_appointment_cannot_create_service_order(): void
    {
        [$doctor, $appointment] = $this->appointment(Appointment::STATUS_PENDING);

        $this->actingAs($doctor)->post(route('appointments.attend', $appointment))->assertStatus(422);
        $this->assertDatabaseCount('service_orders', 0);
    }

    public function test_user_without_permission_cannot_create_service_order(): void
    {
        [, $appointment] = $this->appointment();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->post(route('appointments.attend', $appointment))->assertForbidden();
    }

    public function test_cancelling_an_order_keeps_it_and_audits_cancellation(): void
    {
        [$doctor, $appointment] = $this->appointment();
        $this->actingAs($doctor)->post(route('appointments.attend', $appointment));
        $order = ServiceOrder::firstOrFail();

        app(ServiceOrderService::class)->cancelOrder($order, $doctor);

        $this->assertDatabaseHas('service_orders', ['id' => $order->id, 'status' => ServiceOrder::STATUS_CANCELLED]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'SERVICE_ORDER_CANCELLED', 'subject_id' => $order->id]);
    }

    public function test_emergency_reuses_existing_owner_by_dni_and_creates_an_order_without_payment(): void
    {
        [$doctor] = $this->appointment();
        $ownerUser = User::factory()->create(['role' => User::ROLE_OWNER]);
        $owner = $ownerUser->owner()->create([
            'dni' => '11112222',
            'first_name' => 'Cliente',
            'last_name' => 'Registrado',
            'phone' => '999000111',
            'email' => $ownerUser->email,
        ]);
        $species = Species::firstOrCreate(['name' => 'Felino']);
        $breed = Breed::firstOrCreate(['species_id' => $species->id, 'name' => 'Común']);

        $this->actingAs($doctor)->post(route('appointments.emergency'), [
            'dni' => '11112222',
            'first_name' => 'Cliente Actualizado',
            'phone' => '999333444',
            'pet_name' => 'Michi',
            'species_id' => $species->id,
            'reason' => 'Atención urgente',
        ])->assertRedirect();

        $this->assertDatabaseCount('owners', 2);
        $this->assertDatabaseHas('owners', ['id' => $owner->id, 'phone' => '999333444']);
        $this->assertDatabaseHas('service_orders', ['owner_id' => $owner->id, 'origin' => ServiceOrder::ORIGIN_EMERGENCY, 'payment_status' => ServiceOrder::PAYMENT_PENDING]);
        $this->assertDatabaseHas('pets', ['name' => 'Michi', 'species_id' => $species->id, 'breed_id' => $breed->id]);
    }

    public function test_emergency_creates_provisional_owner_with_hashed_random_credentials(): void
    {
        [$doctor] = $this->appointment();
        $species = Species::firstOrCreate(['name' => 'Ave']);
        Breed::firstOrCreate(['species_id' => $species->id, 'name' => 'Común']);

        $this->actingAs($doctor)->post(route('appointments.emergency'), [
            'dni' => '22223333',
            'first_name' => 'Emergencia',
            'phone' => '999555666',
            'pet_name' => 'Luna',
            'species_id' => $species->id,
            'reason' => 'Accidente',
        ])->assertRedirect();

        $user = User::where('dni', '22223333')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->must_change_username);
        $this->assertNotSame('22223333', $user->password);
        $this->assertDatabaseCount('owners', 2);
        $this->assertDatabaseHas('service_orders', ['origin' => ServiceOrder::ORIGIN_EMERGENCY, 'payment_status' => ServiceOrder::PAYMENT_PENDING]);
    }

    private function appointment(string $status = Appointment::STATUS_CONFIRMED): array
    {
        $doctor = User::factory()->create([
            'role' => User::ROLE_DOCTOR,
            'profile_id' => Profile::where('code', 'DOCTOR')->value('id'),
        ]);
        $profile = $doctor->doctorProfile()->create([
            'dni' => fake()->unique()->numerify('########'),
            'license_number' => fake()->unique()->numerify('CMVP-#####'),
        ]);
        $profile->approve();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER])->owner()->create([
            'dni' => fake()->unique()->numerify('########'),
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'phone' => '999111222',
            'email' => 'ana@example.com',
        ]);
        $species = Species::firstOrCreate(['name' => 'Canino']);
        $breed = Breed::firstOrCreate(['species_id' => $species->id, 'name' => 'Mestizo']);
        $pet = $owner->pets()->create(['name' => 'Bobby', 'species_id' => $species->id, 'breed_id' => $breed->id, 'sex' => 'MACHO']);
        $appointment = Appointment::create([
            'pet_id' => $pet->id,
            'doctor_id' => $profile->id,
            'scheduled_at' => now()->addHour(),
            'reason' => 'Consulta',
            'status' => $status,
        ]);

        return [$doctor->fresh(), $appointment];
    }
}
