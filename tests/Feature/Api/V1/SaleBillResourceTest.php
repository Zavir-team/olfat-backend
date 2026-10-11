<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleBillResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_flattened_bill_shape_with_names_and_raw_amounts(): void
    {
        $user = User::factory()->create(['name' => 'Fatima Saleh']);
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create([
            'name' => 'Ahmad Karimi',
            'phone' => '0700000001',
            'address' => 'Kabul, Afghanistan',
        ]);
        $location = Location::factory()->create(['name' => 'Kabul', 'code' => 'kbl']);
        $variant = ProductVariant::factory()->create([
            'name' => '20kg',
            'base_unit' => 'kg',
        ]);

        $this->seedStock($variant->id, $location->id, $user, 10);

        $create = $this->postJson('/api/v1/sales', [
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'paid_amount' => 50,
            'notes' => 'Delivered at the shop.',
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $create->assertCreated();
        $saleId = $create->json('data.id');

        $response = $this->getJson('/api/v1/sales/'.$saleId);

        $response->assertOk()
            ->assertJsonPath('data.id', $saleId)
            ->assertJsonPath('data.customer', 'Ahmad Karimi')
            ->assertJsonPath('data.customer_phone', '0700000001')
            ->assertJsonPath('data.customer_address', 'Kabul, Afghanistan')
            ->assertJsonPath('data.location', 'Kabul')
            ->assertJsonPath('data.location_code', 'kbl')
            ->assertJsonPath('data.seller', 'Fatima Saleh')
            ->assertJsonPath('data.payment_type', 'mixed')
            ->assertJsonPath('data.notes', 'Delivered at the shop.')
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.total_raw', 200)
            ->assertJsonPath('data.paid', '50.00')
            ->assertJsonPath('data.paid_raw', 50)
            ->assertJsonPath('data.remaining', '150.00')
            ->assertJsonPath('data.remaining_raw', 150)
            ->assertJsonPath('data.items.0.variant', '20kg')
            ->assertJsonPath('data.items.0.unit', 'kg')
            ->assertJsonPath('data.items.0.quantity_raw', 2)
            ->assertJsonPath('data.items.0.unit_price_raw', 100)
            ->assertJsonPath('data.items.0.line_total_raw', 200);
    }

    public function test_show_returns_404_for_missing_sale(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/sales/999999')->assertNotFound();
    }

    private function seedStock(int $variantId, int $locationId, User $user, float $qty): void
    {
        app(StockService::class)->apply([
            'product_variant_id' => $variantId,
            'location_id' => $locationId,
            'type' => StockMovementType::Adjustment,
            'quantity_change' => $qty,
            'user' => $user,
            'notes' => 'test seed',
        ]);
    }
}
