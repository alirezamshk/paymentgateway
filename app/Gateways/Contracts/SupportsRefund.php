<?php

namespace App\Gateways\Contracts;

use App\Gateways\Data\GatewayRefundResult;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;

/**
 * For PSPs with a refund/reverse API. No adapter implements this yet: refunds are
 * out of scope for the first release and must be validated against each PSP's
 * sandbox before being enabled.
 */
interface SupportsRefund
{
    public function refundPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, int $amount): GatewayRefundResult;
}
