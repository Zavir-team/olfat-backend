<?php

namespace Tests\Feature\Api\V1;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_customer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Ahmad',
            'phone' => '0700123456',
            'address' => 'Kabul',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Ahmad')
            ->assertJsonPath('data.balance', '0.00')
            ->assertJsonPath('data.created_by', $user->id);

        $this->assertDatabaseHas('customers', [
            'name' => 'Ahmad',
            'created_by' => $user->id,
        ]);
    }

    public function test_balance_cannot_be_set_from_customer_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Ahmad',
            'balance' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.balance', '0.00');
    }

    public function test_authenticated_user_can_list_and_search_customers(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Ahmad']);
        Customer::factory()->create(['name' => 'Omar']);

        $response = $this->getJson('/api/v1/customers?search=Ahmad');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($response->json('data.data'))->pluck('name');
        $this->assertTrue($names->contains('Ahmad'));
        $this->assertFalse($names->contains('Omar'));
    }

    public function test_authenticated_user_can_update_customer_without_changing_balance(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $customer = Customer::factory()->create([
            'name' => 'Ahmad',
            'balance' => 125.50,
        ]);

        $response = $this->putJson('/api/v1/customers/'.$customer->id, [
            'name' => 'Ahmad Updated',
            'balance' => 999,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Ahmad Updated')
            ->assertJsonPath('data.balance', '125.50');
    }

    public function test_unauthenticated_user_cannot_access_customers(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }
}
