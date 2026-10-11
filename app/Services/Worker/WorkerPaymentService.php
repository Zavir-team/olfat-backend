<?php

namespace App\Services\Worker;

use App\Models\User;
use App\Models\WorkerPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkerPaymentService
{
    /**
     * @param  array{
     *     worker_id:int,
     *     location_id:int,
     *     user:User,
     *     amount:float|string,
     *     paid_at?:\DateTimeInterface|string|null,
     *     description?:string|null
     * }  $data
     */
    public function create(array $data): WorkerPayment
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Worker payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($data, $amount) {
            return WorkerPayment::query()->create([
                'reference_no' => 'WPAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'worker_id' => $data['worker_id'],
                'location_id' => $data['location_id'],
                'user_id' => $data['user']->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now(),
                'description' => $data['description'] ?? null,
            ])->load(['worker', 'location', 'user']);
        });
    }
}
