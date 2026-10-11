<?php

namespace Tests\Feature\Api\V1;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_supplier(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'Tea Import Co',
            'phone' => '0700987654',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Tea Import Co')
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.created_by', $user->id);
    }

    public function test_balance_cannot_be_set_from_supplier_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'Rice Supplier',
            'balance' => 1000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.balance', '0.00');
    }

    public function test_authenticated_user_can_update_supplier(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $supplier = Supplier::factory()->create([
            'name' => 'Old Name',
            'balance' => 80.00,
        ]);

        $response = $this->putJson('/api/v1/suppliers/'.$supplier->id, [
            'name' => 'New Name',
            'balance' => 5000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.balance', '80.00');
    }

    public function test_unauthenticated_user_cannot_access_suppliers(): void
    {
        $this->getJson('/api/v1/suppliers')->assertUnauthorized();
    }
}
