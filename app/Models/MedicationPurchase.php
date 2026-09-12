<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationPurchase extends Model
{
    use HasFactory;

    protected $fillable = ['medication_id', 'user_id', 'receipt_number', 'supplier', 'quantity', 'unit_price_cents', 'total_cents', 'purchased_at', 'batch_number', 'expires_at'];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'expires_at' => 'date'];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUnitPriceAttribute(): string
    {
        return number_format($this->unit_price_cents / 100, 2);
    }

    public function getTotalAttribute(): string
    {
        return number_format($this->total_cents / 100, 2);
    }
}
