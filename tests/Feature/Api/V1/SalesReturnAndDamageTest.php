<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesReturnAndDamageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_return_restores_stock_and_reduces_customer_balance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create(['balance' => 0]);
        $location = Location::factory()->create();
        $variant = ProductVariant::factory()->create();

        $this->seedStock($variant->id, $location->id, $user, 20);

        $saleResponse = $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'paid_amount' => 0,
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 10,
                    'unit_price' => 20,
                ],
            ],
        ])->assertCreated();

        $saleId = $saleResponse->json('data.id');
        $saleItemId = $saleResponse->json('data.items.0.id');
        $this->assertSame('200.00', $customer->fresh()->balance);
        $this->assertSame(10.0, (float) StockBalance::query()->first()->quantity);

        $returnResponse = $this->postJson('/api/v1/sales-returns', [
            'sale_id' => $saleId,
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'quantity' => 4,
                ],
            ],
        ]);

        $returnResponse->assertCreated()
            ->assertJsonPath('data.total_amount', '80.00')
            ->assertJsonPath('data.sale_id', $saleId);

        $this->assertSame(14.0, (float) StockBalance::query()->first()->quantity);
        $this->assertSame('120.00', $customer->fresh()->balance);
        $this->assertSame(4.0, (float) SaleItem::query()->find($saleItemId)->returned_quantity);
    }

    public function test_cannot_return_more_than_sold(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $location = Location::factory()->create();
        $variant = ProductVariant::factory()->create();
        $this->seedStock($variant->id, $location->id, $user, 5);

        $sale = $this->postJson('/api/v1/sales', [
            'customer_id' => Customer::factory()->create()->id,
            'location_id' => $location->id,
            'paid_amount' => 0,
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                    'unit_price' => 10,
                ],
            ],
        ])->assertCreated();

        $this->postJson('/api/v1/sales-returns', [
            'sale_id' => $sale->json('data.id'),
            'items' => [
                [
                    'sale_item_id' => $sale->json('data.items.0.id'),
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_damaged_stock_decreases_available_stock(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $location = Location::factory()->create();
        $variant = ProductVariant::factory()->create(['base_unit' => 'kg']);
        $this->seedStock($variant->id, $location->id, $user, 15);

        $response = $this->postJson('/api/v1/damaged-stocks', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity' => 3,
            'reason' => 'Water damage',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quantity', '3.0000')
            ->assertJsonPath('data.reason', 'Water damage')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertSame(12.0, (float) StockBalance::query()->first()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'damage',
            'product_variant_id' => $variant->id,
            'quantity_change' => -3,
        ]);
    }

    public function test_damaged_stock_fails_when_insufficient(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $location = Location::factory()->create();
        $variant = ProductVariant::factory()->create();
        $this->seedStock($variant->id, $location->id, $user, 1);

        $this->postJson('/api/v1/damaged-stocks', [
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity' => 5,
            'reason' => 'Broken',
        ])->assertStatus(422);

        $this->assertSame(1.0, (float) StockBalance::query()->first()->quantity);
    }

    private function seedStock(int $variantId, int $locationId, User $user, float $qty): void
    {
        app(StockService::class)->apply([
            'product_variant_id' => $variantId,
            'location_id' => $locationId,
            'type' => StockMovementType::Adjustment,
            'quantity_change' => $qty,
            'user' => $user,
        ]);
    }
}
