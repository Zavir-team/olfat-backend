<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreDamagedStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'reason' => ['required', 'string', 'max:255'],
            'damaged_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
