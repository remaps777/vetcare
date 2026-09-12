<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorProfile extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $attributes = [
        'approval_status' => self::STATUS_PENDING,
    ];

    /**
     * approval_status se excluye de fillable para evitar manipulación en registros públicos.
     */
    protected $fillable = [
        'user_id',
        'dni',
        'license_number',
        'specialty_id',
        'specialty',
        'phone',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class, 'doctor_id');
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class, 'doctor_id');
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED;
    }

    public function approve(): void
    {
        $this->approval_status = self::STATUS_APPROVED;
        $this->save();

        if ($this->user) {
            $this->user->is_active = true;
            $this->user->save();
        }
    }

    public function reject(): void
    {
        $this->approval_status = self::STATUS_REJECTED;
        $this->save();

        if ($this->user) {
            $this->user->is_active = false;
            $this->user->save();
        }
    }
}
