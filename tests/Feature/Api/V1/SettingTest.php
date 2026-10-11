<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_can_list_settings(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/settings?group=stock')
            ->assertOk()
            ->assertJsonFragment(['key' => 'stock.low_stock_threshold']);
    }

    public function test_can_update_single_setting(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->putJson('/api/v1/settings/stock.low_stock_threshold', [
            'value' => 8,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.key', 'stock.low_stock_threshold');

        $this->assertEquals(8, $response->json('data.value'));
    }

    public function test_can_bulk_update_settings(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                'app.name' => 'Olfat Updated',
                'app.currency_code' => 'AFN',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/settings/app.name')
            ->assertOk()
            ->assertJsonPath('data.value', 'Olfat Updated');
    }
}
