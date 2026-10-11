<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Models\DamagedStock;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DamagedStockService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * @param  array{
     *     product_variant_id:int,
     *     location_id:int,
     *     user:User,
     *     quantity:float|string,
     *     unit?:string|null,
     *     reason:string,
     *     damaged_at?:\DateTimeInterface|string|null,
     *     notes?:string|null
     * }  $data
     */
    public function create(array $data): DamagedStock
    {
        $quantity = (float) $data['quantity'];
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Damaged quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($data, $quantity) {
            $variant = ProductVariant::query()->findOrFail($data['product_variant_id']);

            $record = DamagedStock::query()->create([
                'reference_no' => 'DMG-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'product_variant_id' => $variant->id,
                'location_id' => $data['location_id'],
                'user_id' => $data['user']->id,
                'quantity' => $quantity,
                'unit' => $data['unit'] ?? $variant->base_unit,
                'reason' => $data['reason'],
                'damaged_at' => $data['damaged_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->stockService->apply([
                'product_variant_id' => $variant->id,
                'location_id' => $data['location_id'],
                'type' => StockMovementType::Damage,
                'quantity_change' => -1 * $quantity,
                'unit' => $record->unit,
                'quantity_in_unit' => $quantity,
                'user' => $data['user'],
                'notes' => 'Damage '.$record->reference_no.': '.$record->reason,
                'occurred_at' => $record->damaged_at,
                'reference_type' => DamagedStock::class,
                'reference_id' => $record->id,
                'allow_negative' => false,
            ]);

            return $record->load(['variant.product', 'location', 'user']);
        });
    }
}
