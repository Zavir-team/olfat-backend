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

    public function test_invoice_and_business_keys_are_seeded(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $expected = [
            'business.legal_name',
            'business.logo_url',
            'business.tax_id',
            'business.address_line1',
            'business.phone',
            'invoice.prefix',
            'invoice.footer_note',
            'invoice.terms',
            'invoice.bank_details',
            'invoice.show_watermark',
            'app.currency_symbol',
            'app.currency_position',
        ];

        $response = $this->getJson('/api/v1/settings');
        $response->assertOk();

        $keys = collect($response->json('data'))->pluck('key')->all();
        foreach ($expected as $key) {
            $this->assertContains($key, $keys, "Expected setting '{$key}' to be seeded.");
        }
    }

    public function test_admin_can_bulk_update_invoice_branding(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/v1/settings', [
            'settings' => [
                'business.legal_name' => 'Olfat Trading Co.',
                'business.tax_id' => 'TIN-12345',
                'invoice.footer_note' => 'Thank you for your business.',
                'invoice.show_watermark' => true,
            ],
        ])->assertOk();

        $this->getJson('/api/v1/settings/business.legal_name')
            ->assertOk()
            ->assertJsonPath('data.value', 'Olfat Trading Co.');

        $this->getJson('/api/v1/settings/business.tax_id')
            ->assertOk()
            ->assertJsonPath('data.value', 'TIN-12345');

        $this->getJson('/api/v1/settings/invoice.show_watermark')
            ->assertOk()
            ->assertJsonPath('data.value', true);
    }

    public function test_staff_cannot_update_invoice_branding(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/settings', [
            'settings' => ['business.legal_name' => 'Hijacked'],
        ])->assertForbidden();
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
