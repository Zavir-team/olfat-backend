<?php

namespace App\Http\Requests\Stock;

use App\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
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
            'quantity_change' => ['required', 'numeric', 'not_in:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'quantity_in_unit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
            // Manual adjustments only in this phase; sales/purchases come later.
            'type' => ['sometimes', Rule::in([StockMovementType::Adjustment->value])],
        ];
    }
}
