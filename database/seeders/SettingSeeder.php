<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'app.name',
                'value' => 'Olfat Business Management',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Application Name',
                'description' => 'Display name for the business system',
            ],
            [
                'key' => 'bill.phone',
                'value' => '',
                'type' => 'string',
                'group' => 'bill',
                'label' => 'Bill Phone',
                'description' => 'Phone number shown on sale bills',
            ],
            [
                'key' => 'bill.address',
                'value' => '',
                'type' => 'string',
                'group' => 'bill',
                'label' => 'Bill Address',
                'description' => 'Address line shown on sale bills',
            ],
            [
                'key' => 'bill.footer_note',
                'value' => 'Thank you for your business. Please keep this bill for your records.',
                'type' => 'string',
                'group' => 'bill',
                'label' => 'Bill Footer Note',
                'description' => 'Footer message printed on sale bills',
            ],
            [
                'key' => 'app.currency_code',
                'value' => 'AFN',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Currency Code',
                'description' => 'OPEN QUESTION: confirm currency with the team',
            ],
            [
                'key' => 'app.timezone',
                'value' => 'Asia/Kabul',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Timezone',
                'description' => 'Default timezone for date/time display',
            ],
            [
                'key' => 'stock.low_stock_threshold',
                'value' => '5',
                'type' => 'decimal',
                'group' => 'stock',
                'label' => 'Low Stock Threshold',
                'description' => 'Dashboard warning when stock quantity is at or below this value',
            ],
            [
                'key' => 'accounting.costing_method',
                'value' => '',
                'type' => 'string',
                'group' => 'accounting',
                'label' => 'Costing Method',
                'description' => 'OPEN QUESTION: fifo | weighted_average — required before P&L',
            ],
            [
                'key' => 'accounting.include_operating_expenses_in_profit',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'accounting',
                'label' => 'Include Operating Expenses in Net Profit',
                'description' => 'OPEN QUESTION: Master Spec §28.2',
            ],
            [
                'key' => 'factory.auto_consume_raw_materials',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'factory',
                'label' => 'Auto Consume Raw Materials on Production',
                'description' => 'OPEN QUESTION: Master Spec §28.6 — do not invent production posting yet',
            ],
            [
                'key' => 'factory.track_factory_material_stock',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'factory',
                'label' => 'Track Factory Material Stock',
                'description' => 'OPEN QUESTION: Master Spec §28.5',
            ],
        ];

        foreach ($defaults as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
