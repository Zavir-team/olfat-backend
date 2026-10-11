<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ProductModule;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogAndStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_product_with_variants(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/products', [
            'module' => ProductModule::Tea->value,
            'name' => 'Green Tea',
            'variants' => [
                [
                    'name' => '20kg',
                    'base_unit' => 'kg',
                    'package_qty' => 20,
                    'package_unit' => 'kg',
                    'sku' => 'TEA-GREEN-20',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Green Tea')
            ->assertJsonPath('data.module', 'tea')
            ->assertJsonCount(1, 'data.variants');

        $this->assertDatabaseHas('product_variants', [
            'name' => '20kg',
            'sku' => 'TEA-GREEN-20',
        ]);
    }

    public function test_stock_adjustment_increases_balance_and_creates_movement(): void
    {
        $user = User::factory()->admin()->create();
        Sanctum::actingAs($user);

        $location = Location::factory()->create();
        $variant = ProductVariant::factory()->create(['base_unit' => 'kg']);

        $response = $this->postJson('/api/v1/stock/adjustments', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_change' => 50,
            'notes' => 'Opening stock',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity_change', '50.0000')
            ->assertJsonPath('data.quantity_after', '50.0000')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('stock_balances', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity' => 50,
        ]);
    }

    public function test_stock_adjustment_blocks_negative_balance_by_default(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $location = Location::factory()->create();
        $variant = ProductVariant::factory()->create();

        StockBalance::query()->create([
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity' => 10,
        ]);

        $response = $this->postJson('/api/v1/stock/adjustments', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_change' => -20,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('stock_balances', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity' => 10,
        ]);
    }

    public function test_can_list_stock_balances_and_movements(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $location = Location::factory()->create();
        $variant = ProductVariant::factory()->create();

        $this->postJson('/api/v1/stock/adjustments', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity_change' => 5,
        ])->assertCreated();

        $this->getJson('/api/v1/stock/balances?location_id='.$location->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/stock/movements?product_variant_id='.$variant->id)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_products_can_be_filtered_by_module(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Product::factory()->create([
            'module' => ProductModule::Tea,
            'name' => 'Green Tea',
            'slug' => 'tea-green',
        ]);
        Product::factory()->create([
            'module' => ProductModule::Rice,
            'name' => 'Rice A',
            'slug' => 'rice-a',
        ]);

        $response = $this->getJson('/api/v1/products?module=tea');

        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('Green Tea'));
        $this->assertFalse($names->contains('Rice A'));
    }
}
