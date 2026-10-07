<?php

namespace App\Http\Requests;

use App\Enums\Currency;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authenticated by AuthenticateClient
    }

    public function rules(): array
    {
        $currency = $this->input('currency', config('payments.default_currency'));
        $limits = config("payments.amount_limits.{$currency}", ['min' => 1, 'max' => PHP_INT_MAX]);

        return [
            'order_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'amount' => [
                'required',
                // Must be a JSON integer: no floats, no numeric strings.
                fn (string $attr, mixed $value, Closure $fail) => is_int($value) ? null : $fail('The amount must be an integer in the currency unit.'),
                'integer',
                'min:'.$limits['min'],
                'max:'.$limits['max'],
            ],
            'currency' => ['sometimes', 'string', Rule::enum(Currency::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'return_url' => ['nullable', 'string', 'max:2048', ...self::urlRules()],
            'merchant_id' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array', 'max:20',
                fn (string $attr, mixed $value, Closure $fail) => strlen((string) json_encode($value)) > 4096 ? $fail('metadata must be at most 4KB.') : null,
            ],
            'new_attempt' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<string> */
    public static function urlRules(): array
    {
        return config('payments.allow_insecure_urls') ? ['url:http,https'] : ['url:https'];
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return $key === null || $key === '' ? null : $key;
    }

    public function after(): array
    {
        return [function ($validator) {
            $key = $this->header('Idempotency-Key');

            if ($key !== null && $key !== '' && ! preg_match('/^[A-Za-z0-9_\-:.]{8,255}$/', $key)) {
                $validator->errors()->add('Idempotency-Key', 'Idempotency-Key must be 8-255 characters of [A-Za-z0-9_-:.].');
            }
        }];
    }
}
