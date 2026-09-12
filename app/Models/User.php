<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PermissionService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'first_name', 'last_name', 'dni', 'phone', 'username', 'email', 'password', 'account_type', 'profile_id', 'is_active', 'must_change_password', 'must_change_username'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';

    public const ROLE_DOCTOR = 'DOCTOR';

    public const ROLE_OWNER = 'OWNER';

    public const ACCOUNT_TYPE_USER = 'USER';

    public const ACCOUNT_TYPE_DOCTOR = 'DOCTOR';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_DOCTOR,
        self::ROLE_OWNER,
    ];

    protected $attributes = [
        'role' => self::ROLE_OWNER,
        'is_active' => true,
        'account_type' => self::ACCOUNT_TYPE_USER,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'must_change_username' => 'boolean',
        ];
    }

    /**
     * Relación con el perfil de propietario (si aplica).
     */
    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

    /**
     * Relación con el perfil profesional de doctor (si aplica).
     */
    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function hasPermission(string $permission): bool
    {
        return app(PermissionService::class)->has($this, $permission);
    }

    public function getOperationalProfileNameAttribute(): string
    {
        return $this->profile?->name ?? 'Sin perfil';
    }

    public function isAdmin(): bool
    {
        return $this->effectiveRole() === self::ROLE_ADMIN;
    }

    public function isDoctor(): bool
    {
        return $this->effectiveRole() === self::ROLE_DOCTOR;
    }

    public function isOwner(): bool
    {
        return $this->effectiveRole() === self::ROLE_OWNER;
    }

    public function accessDeniedReason(): ?string
    {
        if (! in_array($this->role, self::ROLES, true) && ! $this->profile) {
            return 'Tu cuenta no tiene un rol autorizado.';
        }

        $requiresDoctorApproval = $this->isDoctor() && (! $this->profile || $this->profile->code === 'DOCTOR');
        if ($requiresDoctorApproval) {
            $profile = $this->doctorProfile;
            if ($profile?->isPending()) {
                return 'Tu solicitud está pendiente de aprobación por un administrador.';
            }
            if ($profile?->isRejected()) {
                return 'Tu solicitud de doctor fue rechazada. Contacta a la clínica.';
            }
            if (! $profile?->isApproved()) {
                return 'Tu perfil de doctor no está autorizado.';
            }
        }

        return $this->is_active ? null : 'Tu cuenta está inactiva. Contacta a la clínica.';
    }

    public function effectiveRole(): string
    {
        return match ($this->profile?->code) {
            'ADMINISTRADOR' => self::ROLE_ADMIN,
            'DOCTOR' => self::ROLE_DOCTOR,
            'PROPIETARIO' => self::ROLE_OWNER,
            null => $this->role,
            default => 'WORKER',
        };
    }

    public function dashboardRoute(): string
    {
        if ($this->effectiveRole() === 'WORKER') {
            foreach (['ventas.crear' => 'store.cashier', 'inventario.ver' => 'admin.inventory.index', 'inventario.movimientos.ver' => 'admin.inventory.index', 'productos.ver' => 'store.showcase'] as $permission => $route) {
                if ($this->hasPermission($permission)) {
                    return $route;
                }
            }

            return 'worker.dashboard';
        }

        return match ($this->effectiveRole()) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_DOCTOR => 'doctor.dashboard',
            default => 'owner.dashboard',
        };
    }
}
