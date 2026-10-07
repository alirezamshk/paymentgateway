<?php

namespace App\Gateways\Contracts;

use App\Gateways\Data\GatewayCheckResult;
use App\Models\Merchant;

/** For PSPs where merchant credentials can be tested without moving money. */
interface SupportsCredentialCheck
{
    public function checkCredentials(Merchant $merchant): GatewayCheckResult;
}
