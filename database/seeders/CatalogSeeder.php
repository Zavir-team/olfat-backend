<?php

namespace Database\Seeders;

use App\Enums\ProductModule;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTea();
        $this->seedRice();
        $this->seedDetergent();
        $this->seedRawMaterials();
    }

    private function seedTea(): void
    {
        foreach (['Green Tea', 'Black Tea'] as $name) {
            $product = $this->product(ProductModule::Tea, $name);
            foreach ([20, 30, 35, 50, 68] as $kg) {
                $this->variant($product, "{$kg}kg", 'kg', $kg, 'kg', "TEA-".Str::upper(Str::slug($name))."-{$kg}");
            }
        }
    }

    private function seedRice(): void
    {
        // OPEN QUESTION: exact rice type/quality list — seeded as a generic starter.
        $product = $this->product(ProductModule::Rice, 'Rice (General)');
        foreach ([20, 24.5] as $kg) {
            $label = rtrim(rtrim(number_format($kg, 1, '.', ''), '0'), '.');
            $this->variant($product, "{$label}kg", 'kg', $kg, 'kg', "RICE-GEN-{$label}");
        }
    }

    private function seedDetergent(): void
    {
        $items = [
            ['Vaseline', [
                ['6 pieces/package', 'piece', 6, 'piece'],
                ['Kilogram', 'kg', 1, 'kg'],
            ]],
            ['Liquid Dishwashing', [
                ['3 bottles', 'bottle', 3, 'bottle'],
                ['6 bottles', 'bottle', 6, 'bottle'],
                ['12 bottles', 'bottle', 12, 'bottle'],
                ['20-liter drum', 'liter', 20, 'liter'],
                ['Kilogram', 'kg', 1, 'kg'],
            ]],
            ['Shampoo', [
                ['16 pieces/package', 'piece', 16, 'piece'],
            ]],
            ['Powder', [
                ['1kg', 'kg', 1, 'kg'],
                ['2kg', 'kg', 2, 'kg'],
                ['5kg', 'kg', 5, 'kg'],
                ['10kg bag', 'kg', 10, 'kg'],
                ['20kg bag', 'kg', 20, 'kg'],
            ]],
            ['Soap', [
                ['Carton (configurable)', 'carton', 1, 'carton'],
            ]],
        ];

        foreach ($items as [$name, $variants]) {
            $product = $this->product(ProductModule::Detergent, $name);
            foreach ($variants as [$vName, $base, $qty, $pkgUnit]) {
                $sku = 'DET-'.Str::upper(Str::slug($name)).'-'.Str::upper(Str::slug($vName));
                $this->variant($product, $vName, $base, $qty, $pkgUnit, substr($sku, 0, 80));
            }
        }
    }

    private function seedRawMaterials(): void
    {
        $product = $this->product(ProductModule::RawMaterial, 'Factory Raw Material');
        foreach ([170, 220] as $kg) {
            $this->variant($product, "{$kg}kg bag", 'kg', $kg, 'kg', "RAW-{$kg}");
        }
    }

    private function product(ProductModule $module, string $name): Product
    {
        $slug = Str::slug($module->value.'-'.$name);

        return Product::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'module' => $module,
                'name' => $name,
                'description' => null,
                'is_active' => true,
            ]
        );
    }

    private function variant(
        Product $product,
        string $name,
        string $baseUnit,
        float|int $packageQty,
        string $packageUnit,
        string $sku
    ): void {
        $product->variants()->updateOrCreate(
            ['name' => $name],
            [
                'sku' => $sku,
                'base_unit' => $baseUnit,
                'package_qty' => $packageQty,
                'package_unit' => $packageUnit,
                'is_active' => true,
            ]
        );
    }
}
