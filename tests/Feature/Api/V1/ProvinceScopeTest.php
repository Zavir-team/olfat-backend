<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProvinceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_sales_from_all_provinces(): void
    {
        $kabul = Location::factory()->create(['name' => 'Kabul']);
        $ghazni = Location::factory()->create(['name' => 'Ghazni']);
        $user = User::factory()->create(['location_id' => $kabul->id]);
        Sanctum::actingAs($user);

        Sale::factory()->create(['location_id' => $kabul->id, 'user_id' => $user->id]);
        Sale::factory()->create(['location_id' => $ghazni->id, 'user_id' => $user->id]);

        $response = $this->getJson('/api/v1/sales');
        $response->assertOk()->assertJsonPath('success', true);

        $rows = $response->json('data.data') ?? $response->json('data');
        $this->assertCount(2, $rows);
    }

    public function test_user_cannot_create_sale_in_another_province(): void
    {
        $kabul = Location::factory()->create(['name' => 'Kabul']);
        $ghazni = Location::factory()->create(['name' => 'Ghazni']);
        $user = User::factory()->create(['location_id' => $kabul->id]);
        Sanctum::actingAs($user);

        $variant = ProductVariant::factory()->create();
        app(StockService::class)->apply([
            'product_variant_id' => $variant->id,
            'location_id' => $ghazni->id,
            'type' => StockMovementType::Purchase,
            'quantity_change' => 50,
            'user' => $user,
        ]);

        $this->postJson('/api/v1/sales', [
            'customer_id' => Customer::factory()->create()->id,
            'location_id' => $ghazni->id,
            'paid_amount' => 0,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 10],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['location_id']);
    }

    public function test_user_create_sale_is_forced_to_own_province(): void
    {
        $kabul = Location::factory()->create(['name' => 'Kabul']);
        $user = User::factory()->create(['location_id' => $kabul->id]);
        Sanctum::actingAs($user);

        $variant = ProductVariant::factory()->create();
        app(StockService::class)->apply([
            'product_variant_id' => $variant->id,
            'location_id' => $kabul->id,
            'type' => StockMovementType::Purchase,
            'quantity_change' => 10,
            'user' => $user,
        ]);

        $this->postJson('/api/v1/sales', [
            'customer_id' => Customer::factory()->create()->id,
            'location_id' => $kabul->id,
            'paid_amount' => 0,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 10],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.location_id', $kabul->id);
    }
}
