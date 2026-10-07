<?php

namespace App\Gateways\Contracts;

use App\Gateways\Data\GatewaySettleResult;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;

/** For PSPs that need an explicit settlement call after a successful verify. */
interface SupportsSettlement
{
    public function settlePayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewaySettleResult;
}
