<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemType extends Model
{
    use HasFactory;

    public const PRODUCT = 'PRODUCT';

    public const MEDICATION = 'MEDICATION';

    public const BOTH = 'BOTH';

    protected $fillable = ['name', 'applies_to', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(Medication::class);
    }

    public function getAppliesToLabelAttribute(): string
    {
        return ['PRODUCT' => 'Producto', 'MEDICATION' => 'Medicamento', 'BOTH' => 'Ambos'][$this->applies_to] ?? $this->applies_to;
    }
}
