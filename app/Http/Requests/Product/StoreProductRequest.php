<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge([
                'slug' => Str::slug($this->string('name')).'-'.Str::lower(Str::random(4)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'module' => ['required', Rule::in(ProductModule::values())],
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'variants' => ['sometimes', 'array'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:120'],
            'variants.*.sku' => ['nullable', 'string', 'max:80', 'unique:product_variants,sku'],
            'variants.*.base_unit' => ['required_with:variants', 'string', 'max:30'],
            'variants.*.package_qty' => ['nullable', 'numeric', 'min:0'],
            'variants.*.package_unit' => ['nullable', 'string', 'max:30'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
