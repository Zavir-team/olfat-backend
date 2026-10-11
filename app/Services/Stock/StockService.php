<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StockService
{
    /**
     * Apply a stock change in a DB transaction with row lock.
     * Quantity is always stored/changed in the variant base_unit.
     *
     * @param  array{
     *     product_variant_id:int,
     *     location_id:int,
     *     type:StockMovementType|string,
     *     quantity_change:float|string,
     *     unit:?string,
     *     quantity_in_unit?:float|string|null,
     *     user:User,
     *     notes?:string|null,
     *     occurred_at?:\DateTimeInterface|string|null,
     *     reference_type?:string|null,
     *     reference_id?:int|null,
     *     allow_negative?:bool
     * }  $data
     */
    public function apply(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $variant = ProductVariant::query()->findOrFail($data['product_variant_id']);
            $type = $data['type'] instanceof StockMovementType
                ? $data['type']
                : StockMovementType::from($data['type']);

            $quantityChange = (float) $data['quantity_change'];
            if ($quantityChange == 0.0) {
                throw new InvalidArgumentException('quantity_change cannot be zero.');
            }

            $locationId = (int) $data['location_id'];
            $balance = $this->lockedBalance((int) $variant->id, $locationId);

            $available = (float) $balance->quantity;
            $newQuantity = $available + $quantityChange;
            $allowNegative = (bool) ($data['allow_negative'] ?? false);

            if (! $allowNegative && $newQuantity < 0) {
                $locationName = Location::query()->whereKey($locationId)->value('name') ?: 'this province';
                $unit = (string) ($data['unit'] ?? $variant->base_unit ?? '');
                $requested = abs($quantityChange);

                throw new RuntimeException($this->insufficientStockMessage(
                    $available,
                    $requested,
                    $unit,
                    $locationName
                ));
            }

            $balance->quantity = $newQuantity;
            $balance->save();

            return StockMovement::query()->create([
                'product_variant_id' => $variant->id,
                'location_id' => $locationId,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'quantity_after' => $newQuantity,
                'unit' => $data['unit'] ?? $variant->base_unit,
                'quantity_in_unit' => $data['quantity_in_unit'] ?? abs($quantityChange),
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'user_id' => $data['user']->id,
                'notes' => $data['notes'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);
        });
    }

    private function insufficientStockMessage(float $available, float $requested, string $unit, string $locationName): string
    {
        $unitLabel = $unit !== '' ? ' '.$unit : '';

        return sprintf(
            'You have only %s%s in %s. You cannot sell or remove %s%s from this province.',
            $this->formatQuantity($available),
            $unitLabel,
            $locationName,
            $this->formatQuantity($requested),
            $unitLabel
        );
    }

    private function formatQuantity(float $quantity): string
    {
        if (abs($quantity - round($quantity)) < 0.0001) {
            return (string) (int) round($quantity);
        }

        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }

    private function lockedBalance(int $variantId, int $locationId): StockBalance
    {
        $balance = StockBalance::query()
            ->where('product_variant_id', $variantId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($balance) {
            return $balance;
        }

        try {
            StockBalance::query()->insertOrIgnore([
                'product_variant_id' => $variantId,
                'location_id' => $locationId,
                'quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            // Concurrent insert lost the race; fall through and lock the winner.
        }

        return StockBalance::query()
            ->where('product_variant_id', $variantId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
