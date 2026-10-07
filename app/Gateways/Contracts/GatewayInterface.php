<?php

namespace App\Gateways\Contracts;

use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\Data\GatewayVerifyResult;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;

/**
 * Contract every PSP adapter implements. Optional operations (settlement, refund,
 * credential check) live in separate capability interfaces so an adapter only
 * declares what the PSP really supports.
 */
interface GatewayInterface
{
    /** Provider code, matching gateway_providers.code. */
    public function code(): string;

    /**
     * Credential keys this PSP needs on a Merchant.
     * Standard keys: merchant_identifier, terminal_identifier, username, password, api_key.
     * Any other key is stored in the merchant's encrypted config.
     *
     * @return array<string, string> key => human readable label
     */
    public function requiredCredentials(): array;

    /** Request a payment token/authority from the PSP. Must not mark anything as paid. */
    public function createPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewayCreateResult;

    /**
     * Does this callback belong to the given attempt? Used to reject callbacks whose
     * authority/token/invoice does not match what we issued.
     *
     * @param  array<string, mixed>  $callbackData
     */
    public function callbackMatchesAttempt(PaymentAttempt $attempt, array $callbackData): bool;

    /**
     * Ask the PSP for the authoritative result. Callback fields alone are never trusted.
     * Throws App\Exceptions\GatewayException when the outcome is unknown (timeout, 5xx).
     *
     * @param  array<string, mixed>  $callbackData
     */
    public function verifyPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, array $callbackData): GatewayVerifyResult;
}
