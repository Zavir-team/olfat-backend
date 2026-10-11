<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'module' => ['sometimes', 'required', Rule::in(ProductModule::values())],
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:190',
                Rule::unique('products', 'slug')->ignore($productId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
