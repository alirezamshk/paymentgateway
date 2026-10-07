<?php

namespace App\Payments;

use App\Enums\RecordStatus;
use App\Exceptions\ApiException;
use App\Gateways\GatewayManager;
use App\Models\Client;
use App\Models\Merchant;

/**
 * Picks the merchant for a payment. Lookups are always scoped to the authenticated
 * client, so a client can never use another client's merchant.
 */
class MerchantResolver
{
    public function __construct(private readonly GatewayManager $gateways) {}

    public function resolve(Client $client, ?string $merchantPublicId): Merchant
    {
        $query = $client->merchants()->active()->with('provider');

        $merchant = $merchantPublicId !== null
            ? $query->where('public_id', $merchantPublicId)->first()
            : $query->where('is_default', true)->first();

        if ($merchant === null) {
            throw new ApiException(
                'MERCHANT_NOT_FOUND',
                $merchantPublicId !== null
                    ? 'The requested merchant is not available for this client.'
                    : 'No active default merchant is available for this client.',
                422,
            );
        }

        if ($merchant->provider === null
            || $merchant->provider->status !== RecordStatus::Active
            || ! $this->gateways->has($merchant->provider->code)) {
            throw new ApiException('PROVIDER_UNAVAILABLE', 'The payment provider for this merchant is currently unavailable.', 422);
        }

        return $merchant;
    }
}
