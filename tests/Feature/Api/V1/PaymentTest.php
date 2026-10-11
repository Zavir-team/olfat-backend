<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_payment_reduces_receivable_balance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $customer = Customer::factory()->create(['balance' => 500]);
        $location = Location::factory()->create();

        $response = $this->postJson('/api/v1/customer-payments', [
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'amount' => 150,
            'notes' => 'Partial payment',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', '150.00')
            ->assertJsonPath('data.balance_before', '500.00')
            ->assertJsonPath('data.balance_after', '350.00')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertSame('350.00', $customer->fresh()->balance);
    }

    public function test_customer_payment_cannot_exceed_balance_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $customer = Customer::factory()->create(['balance' => 100]);

        $response = $this->postJson('/api/v1/customer-payments', [
            'customer_id' => $customer->id,
            'location_id' => Location::factory()->create()->id,
            'amount' => 150,
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame('100.00', $customer->fresh()->balance);
    }

    public function test_customer_payment_rejects_sale_for_other_customer(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $customer = Customer::factory()->create(['balance' => 200]);
        $otherCustomer = Customer::factory()->create();
        $sale = Sale::factory()->create(['customer_id' => $otherCustomer->id]);

        $response = $this->postJson('/api/v1/customer-payments', [
            'customer_id' => $customer->id,
            'location_id' => Location::factory()->create()->id,
            'amount' => 50,
            'sale_id' => $sale->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_supplier_payment_reduces_payable_balance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $supplier = Supplier::factory()->create(['balance' => 800]);
        $location = Location::factory()->create();

        $response = $this->postJson('/api/v1/supplier-payments', [
            'supplier_id' => $supplier->id,
            'location_id' => $location->id,
            'amount' => 300,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.balance_before', '800.00')
            ->assertJsonPath('data.balance_after', '500.00')
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertSame('500.00', $supplier->fresh()->balance);
    }

    public function test_supplier_payment_cannot_exceed_balance_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $supplier = Supplier::factory()->create(['balance' => 50]);

        $this->postJson('/api/v1/supplier-payments', [
            'supplier_id' => $supplier->id,
            'location_id' => Location::factory()->create()->id,
            'amount' => 100,
        ])->assertStatus(422);

        $this->assertSame('50.00', $supplier->fresh()->balance);
    }

    public function test_can_list_customer_and_supplier_payments(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $customer = Customer::factory()->create(['balance' => 100]);
        $supplier = Supplier::factory()->create(['balance' => 100]);
        $location = Location::factory()->create();

        $this->postJson('/api/v1/customer-payments', [
            'customer_id' => $customer->id,
            'location_id' => $location->id,
            'amount' => 40,
        ])->assertCreated();

        $this->postJson('/api/v1/supplier-payments', [
            'supplier_id' => $supplier->id,
            'location_id' => $location->id,
            'amount' => 25,
        ])->assertCreated();

        $this->getJson('/api/v1/customer-payments?customer_id='.$customer->id)->assertOk();
        $this->getJson('/api/v1/supplier-payments?supplier_id='.$supplier->id)->assertOk();
    }
}
