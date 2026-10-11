<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:filter', 'max:190'],
            'password' => ['required', 'string'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}