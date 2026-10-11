<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'unique:locations,name'],
            'code' => ['required', 'string', 'max:50', 'unique:locations,code'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
