<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Medication extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'presentation', 'distributor_name', 'distributor_id', 'item_type_id', 'presentation_quantity', 'presentation_unit', 'purchase_price_cents', 'minimum_stock', 'is_active'];

    protected $attributes = ['stock' => 0, 'is_active' => true];

    protected function casts(): array
    {
        return ['purchase_price_cents' => 'integer', 'stock' => 'integer', 'minimum_stock' => 'integer', 'is_active' => 'boolean'];
    }

    public function getPurchasePriceAttribute(): string
    {
        return number_format($this->purchase_price_cents / 100, 2, '.', '');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function getPresentationDisplayAttribute(): string
    {
        if ($this->presentation_quantity !== null && $this->presentation_unit) {
            return rtrim(rtrim((string) $this->presentation_quantity, '0'), '.').' '.$this->presentation_unit;
        }

        return $this->presentation ?: '—';
    }
}
