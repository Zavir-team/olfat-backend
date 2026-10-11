<?php

namespace App\Services\Payment;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CustomerPaymentService
{
    /** Retries for the whole unit of work when the DBMS reports a deadlock. */
    private const DEADLOCK_RETRIES = 3;

    /**
     * @param  array{
     *     customer_id:int,
     *     location_id:int,
     *     user:User,
     *     amount:float|string,
     *     sale_id?:int|null,
     *     paid_at?:\DateTimeInterface|string|null,
     *     notes?:string|null
     * }  $data
     */
    public function create(array $data): CustomerPayment
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($data, $amount) {
            $customer = Customer::query()
                ->whereKey($data['customer_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $sale = null;
            if (! empty($data['sale_id'])) {
                $sale = Sale::query()
                    ->whereKey($data['sale_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $sale->customer_id !== (int) $customer->id) {
                    throw new InvalidArgumentException('Sale does not belong to this customer.');
                }

                $saleRemaining = round((float) $sale->remaining_amount, 2);
                if ($amount > $saleRemaining) {
                    throw new InvalidArgumentException('Payment amount cannot exceed the sale remaining balance.');
                }
            }

            $balanceBefore = round((float) $customer->balance, 2);

            if ($amount > $balanceBefore) {
                throw new InvalidArgumentException('Payment amount cannot exceed customer balance.');
            }

            $balanceAfter = round($balanceBefore - $amount, 2);

            $payment = CustomerPayment::query()->create([
                'reference_no' => 'CPAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'customer_id' => $customer->id,
                'location_id' => $data['location_id'],
                'user_id' => $data['user']->id,
                'sale_id' => $sale?->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $customer->balance = $balanceAfter;
            $customer->save();

            if ($sale) {
                $sale->paid_amount = round((float) $sale->paid_amount + $amount, 2);
                $sale->remaining_amount = round((float) $sale->remaining_amount - $amount, 2);
                $sale->payment_type = $this->resolvePaymentType(
                    (float) $sale->paid_amount,
                    (float) $sale->remaining_amount
                );
                $sale->save();
            }

            return $payment->load(['customer', 'location', 'user', 'sale']);
        }, attempts: self::DEADLOCK_RETRIES);
    }

    private function resolvePaymentType(float $paid, float $remaining): string
    {
        if ($remaining <= 0) {
            return 'cash';
        }
        if ($paid <= 0) {
            return 'credit';
        }

        return 'mixed';
    }
}
