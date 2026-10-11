<?php

namespace Tests\Feature\Api\V1;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_locations(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Location::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/locations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_create_location(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/locations', [
            'name' => 'Kabul',
            'code' => 'kabul',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Kabul')
            ->assertJsonPath('data.code', 'kabul');

        $this->assertDatabaseHas('locations', ['code' => 'kabul']);
    }
}
