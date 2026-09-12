<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_uses_database_price_and_updates_stock_after_confirmation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $medicationPayload = [
            'code' => 'AMOX-01',
            'name' => 'Amoxicilina',
            'presentation' => 'Caja x 10',
            'purchase_price' => '12.50',
            'minimum_stock' => '5',
            'is_active' => '1',
            '_preview' => '1',
        ];

        $medicationPreview = $this->actingAs($admin)->postJson('/admin/medications', $medicationPayload)
            ->assertOk()->json();
        $this->actingAs($admin)->postJson('/admin/medications', [
            ...$medicationPayload,
            '_preview' => '0',
            'confirmation_token' => $medicationPreview['confirmation_token'],
        ])->assertOk();

        $medication = Medication::firstOrFail();
        $purchasePayload = [
            'medication_id' => $medication->id,
            'receipt_number' => 'FAC-001',
            'supplier' => 'Proveedor',
            'quantity' => '4',
            'purchased_at' => now()->toDateString(),
            '_preview' => '1',
            'unit_price_cents' => '1',
            'total_cents' => '1',
        ];

        $purchasePreview = $this->actingAs($admin)->postJson('/admin/purchases', $purchasePayload)
            ->assertUnprocessable();
        unset($purchasePayload['unit_price_cents'], $purchasePayload['total_cents']);
        $purchasePayload['_preview'] = '1';
        $purchasePreview = $this->actingAs($admin)->postJson('/admin/purchases', $purchasePayload)
            ->assertOk()->json();

        $this->actingAs($admin)->postJson('/admin/purchases', [
            ...$purchasePayload,
            '_preview' => '0',
            'confirmation_token' => $purchasePreview['confirmation_token'],
        ])->assertOk();

        $this->assertSame(4, $medication->fresh()->stock);
        $this->assertDatabaseHas('medication_purchases', [
            'receipt_number' => 'FAC-001',
            'unit_price_cents' => 1250,
            'total_cents' => 5000,
        ]);
    }
}
