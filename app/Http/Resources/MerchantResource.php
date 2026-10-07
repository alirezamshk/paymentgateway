<?php

namespace App\Http\Resources;

use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never includes credentials - only which credential keys are configured.
 *
 * @mixin Merchant
 */
class MerchantResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'merchant_id' => $this->public_id,
            'name' => $this->name,
            'provider' => $this->provider?->code,
            'status' => $this->status->value,
            'is_default' => $this->is_default,
            'configured_credentials' => array_keys($this->credentials()),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
