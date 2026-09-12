<?php

namespace App\Models;

use Database\Factories\WorkerApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkerApplication extends Model
{
    /** @use HasFactory<WorkerApplicationFactory> */
    use HasFactory;

    public const PROFILES = ['DOCTOR', 'CAJA', 'INVENTARIO'];

    public const STATUSES = ['PENDING' => 'Pendiente', 'ON_HOLD' => 'En espera', 'APPROVED' => 'Aprobada', 'REJECTED' => 'Rechazada'];

    protected $fillable = ['first_name', 'last_name', 'dni', 'phone', 'email', 'username', 'requested_profile_id', 'specialty_id', 'license_number', 'password'];

    protected $hidden = ['password'];

    protected $attributes = ['status' => 'PENDING'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'reviewed_at' => 'datetime'];
    }

    public function requestedProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'requested_profile_id');
    }

    public function assignedProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'assigned_profile_id');
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')->where('subject_type', self::class)->orderBy('id');
    }
}
