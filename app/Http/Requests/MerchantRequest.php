<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'provider' => [$creating ? 'required' : 'sometimes', 'string', 'max:50'],
            'credentials' => ['sometimes', 'array', 'max:20'],
            'credentials.*' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'in:active,disabled'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
