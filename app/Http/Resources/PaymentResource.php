<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->public_id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'description' => $this->description,
            'customer' => $this->customer_mobile || $this->customer_username || $this->customer_name ? [
                'mobile' => $this->customer_mobile,
                'username' => $this->customer_username,
                'name' => $this->customer_name,
            ] : null,
            'status' => $this->status->value,
            'payment_url' => $this->status->isPayable() ? $this->payment_url : null,
            'merchant_id' => $this->merchant?->public_id,
            'provider' => $this->provider?->code,
            'reference_number' => $this->reference_number,
            'trace_number' => $this->trace_number,
            'card_mask' => $this->card_mask,
            'return_url' => $this->return_url,
            'metadata' => $this->metadata,
            'attempts' => $this->attempts_count,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'paid_at' => $this->paid_at?->toIso8601ZuluString(),
            'expires_at' => $this->expires_at?->toIso8601ZuluString(),
        ];
    }
}
