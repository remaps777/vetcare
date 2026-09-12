<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = ['warehouse_id', 'item_type', 'item_id', 'movement_type', 'medication_id', 'user_id', 'pet_id', 'quantity', 'balance', 'balance_before', 'balance_after', 'reason', 'reference_type', 'reference_id'];

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function displayMovementType(): string
    {
        return match ($this->movement_type) {
            'ENTRY' => 'Entrada',
            'EXIT' => 'Salida',
            'TRANSFER_IN' => 'Transferencia recibida',
            'TRANSFER_OUT' => 'Transferencia enviada',
            'ADJUSTMENT_IN' => 'Ajuste de entrada',
            'ADJUSTMENT_OUT' => 'Ajuste de salida',
            default => 'Movimiento',
        };
    }

    public function displayItemType(): string
    {
        return $this->item_type === 'medication' ? 'Medicamento' : 'Producto';
    }
}
