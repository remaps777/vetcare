<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Pet extends Model
{
    use HasFactory;

    public const SEX_MACHO = 'MACHO';

    public const SEX_HEMBRA = 'HEMBRA';

    public const SEXES = [
        self::SEX_MACHO,
        self::SEX_HEMBRA,
    ];

    protected $fillable = [
        'owner_id',
        'species_id',
        'breed_id',
        'name',
        'sex',
        'birth_date',
        'weight',
        'color',
        'observations',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Pet $pet) {
            if ($pet->breed_id && $pet->species_id) {
                $breed = Breed::find($pet->breed_id);
                if ($breed && (int) $breed->species_id !== (int) $pet->species_id) {
                    throw new InvalidArgumentException('La raza seleccionada no pertenece a la especie indicada.');
                }
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }
}
