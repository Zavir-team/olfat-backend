<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('product_variant');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'sku' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('product_variants', 'sku')->ignore($variantId),
            ],
            'base_unit' => ['sometimes', 'required', 'string', 'max:30'],
            'package_qty' => ['nullable', 'numeric', 'min:0'],
            'package_unit' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
