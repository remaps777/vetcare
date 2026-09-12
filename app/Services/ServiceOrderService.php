<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Breed;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Profile;
use App\Models\ServiceOrder;
use App\Models\Species;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceOrderService
{
    public function getOrCreateForAppointment(Appointment $appointment, User $actor): ServiceOrder
    {
        abort_unless($appointment->status === Appointment::STATUS_CONFIRMED, 422, 'Solo las citas confirmadas pueden generar una orden de atención.');

        if ($actor->doctorProfile && (int) $actor->doctorProfile->id !== (int) $appointment->doctor_id) {
            abort(403);
        }

        return DB::transaction(function () use ($appointment, $actor): ServiceOrder {
            $lockedAppointment = Appointment::query()->with('pet.owner')->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $existing = ServiceOrder::query()->where('appointment_id', $lockedAppointment->id)->first();
            if ($existing) {
                return $existing->load(['owner', 'pet', 'doctor.user', 'appointment']);
            }

            $order = ServiceOrder::create([
                'owner_id' => $lockedAppointment->pet->owner_id,
                'pet_id' => $lockedAppointment->pet_id,
                'appointment_id' => $lockedAppointment->id,
                'doctor_id' => $lockedAppointment->doctor_id,
                'origin' => ServiceOrder::ORIGIN_APPOINTMENT,
                'status' => ServiceOrder::STATUS_OPEN,
                'payment_status' => ServiceOrder::PAYMENT_PENDING,
                'created_by_user_id' => $actor->id,
            ]);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'SERVICE_ORDER_CREATED',
                'subject_type' => ServiceOrder::class,
                'subject_id' => $order->id,
                'before_data' => null,
                'after_data' => $order->attributesToArray(),
            ]);

            return $order->load(['owner', 'pet', 'doctor.user', 'appointment']);
        });
    }

    public function getOrder(ServiceOrder $order): ServiceOrder
    {
        return $order->load(['owner', 'pet', 'doctor.user', 'appointment']);
    }

    /** @param array{dni: string, first_name: string, phone: string, pet_name: string, species_id: int, reason: string} $data */
    public function createEmergencyOrder(array $data, User $actor): ServiceOrder
    {
        return DB::transaction(function () use ($data, $actor): ServiceOrder {
            $owner = Owner::query()->with('user')->where('dni', $data['dni'])->lockForUpdate()->first();
            $temporaryCredentials = false;

            if (! $owner) {
                $lastName = 'Provisional';
                $email = 'emergency.'.Str::lower(Str::random(20)).'@vetcare.local';
                $username = $this->uniqueTemporaryUsername($data['dni']);
                $user = User::create([
                    'name' => trim($data['first_name'].' '.$lastName),
                    'first_name' => $data['first_name'],
                    'last_name' => $lastName,
                    'dni' => $data['dni'],
                    'phone' => $data['phone'],
                    'username' => $username,
                    'email' => $email,
                    'password' => Str::password(32),
                    'role' => User::ROLE_OWNER,
                    'account_type' => User::ACCOUNT_TYPE_USER,
                    'profile_id' => Profile::where('code', 'PROPIETARIO')->value('id'),
                    'is_active' => true,
                    'must_change_password' => true,
                    'must_change_username' => true,
                ]);
                $owner = $user->owner()->create([
                    'dni' => $data['dni'],
                    'first_name' => $data['first_name'],
                    'last_name' => $lastName,
                    'phone' => $data['phone'],
                    'email' => $email,
                    'is_active' => true,
                ]);
                $temporaryCredentials = true;
            } else {
                $owner->update([
                    'first_name' => $data['first_name'],
                    'phone' => $data['phone'],
                ]);
            }

            $species = Species::query()->whereKey($data['species_id'])->where('is_active', true)->firstOrFail();
            $breed = Breed::query()->where('species_id', $species->id)->where('is_active', true)->first();
            if (! $breed) {
                throw new \RuntimeException('La especie seleccionada no tiene una raza activa configurada.');
            }

            $pet = $owner->pets()->create([
                'name' => $data['pet_name'],
                'species_id' => $species->id,
                'breed_id' => $breed->id,
                'sex' => Pet::SEX_MACHO,
                'observations' => 'Mascota registrada desde atención de emergencia. Motivo: '.$data['reason'],
                'is_active' => true,
            ]);
            $order = ServiceOrder::create([
                'owner_id' => $owner->id,
                'pet_id' => $pet->id,
                'origin' => ServiceOrder::ORIGIN_EMERGENCY,
                'status' => ServiceOrder::STATUS_OPEN,
                'payment_status' => ServiceOrder::PAYMENT_PENDING,
                'created_by_user_id' => $actor->id,
            ]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'SERVICE_ORDER_CREATED',
                'subject_type' => ServiceOrder::class,
                'subject_id' => $order->id,
                'before_data' => null,
                'after_data' => [...$order->attributesToArray(), 'temporary_credentials' => $temporaryCredentials],
            ]);

            return $order->load(['owner', 'pet.species', 'doctor.user']);
        });
    }

    public function cancelOrder(ServiceOrder $order, User $actor): ServiceOrder
    {
        return DB::transaction(function () use ($order, $actor): ServiceOrder {
            $order = ServiceOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $before = $order->attributesToArray();
            $order->update(['status' => ServiceOrder::STATUS_CANCELLED]);
            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'SERVICE_ORDER_CANCELLED',
                'subject_type' => ServiceOrder::class,
                'subject_id' => $order->id,
                'before_data' => $before,
                'after_data' => $order->fresh()->attributesToArray(),
            ]);

            return $order->fresh();
        });
    }

    private function uniqueTemporaryUsername(string $dni): string
    {
        $base = Str::lower($dni);
        $username = $base;
        $suffix = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base.'.'.$suffix++;
        }

        return $username;
    }
}
