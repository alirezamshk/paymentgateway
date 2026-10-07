<?php

namespace App\Gateways\Adapters;

use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\Data\RedirectInstruction;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Internal fake PSP for tests and local development. It never talks to a bank.
 * GatewayManager refuses it in production and when GATEWAY_SANDBOX_ENABLED is false.
 *
 * The customer is sent to /sandbox-psp/{token}, chooses success or failure, and is
 * redirected to the normal callback route. Verification reads that stored outcome.
 */
class SandboxGateway extends AbstractGateway
{
    public const CACHE_PREFIX = 'sandbox-psp:';

    public function code(): string
    {
        return 'sandbox';
    }

    public function requiredCredentials(): array
    {
        return [];
    }

    public function createPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewayCreateResult
    {
        $token = 'sbx_'.Str::random(32);

        Cache::put(self::CACHE_PREFIX.$token, [
            'amount' => $payment->amount,
            'currency' => $payment->currency->value,
            'callback_url' => $this->callbackUrl($payment),
            'outcome' => null,
        ], now()->addHours(2));

        return GatewayCreateResult::success(
            new RedirectInstruction(route('sandbox.psp.show', ['token' => $token])),
            authority: $token,
            token: $token,
            request: ['amount' => $payment->amount],
            response: ['token' => $token],
        );
    }

    public function callbackMatchesAttempt(PaymentAttempt $attempt, array $callbackData): bool
    {
        return isset($callbackData['token']) && $attempt->token !== null && hash_equals($attempt->token, (string) $callbackData['token']);
    }

    public function verifyPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, array $callbackData): GatewayVerifyResult
    {
        $state = Cache::get(self::CACHE_PREFIX.$attempt->token);

        if (! is_array($state) || ($state['outcome'] ?? null) !== 'success') {
            return GatewayVerifyResult::rejected('SANDBOX_DECLINED', 'Sandbox payment was not completed.');
        }

        if ((int) $state['amount'] !== $payment->amount) {
            return GatewayVerifyResult::rejected('AMOUNT_MISMATCH', 'Sandbox amount mismatch.');
        }

        return GatewayVerifyResult::verified(
            referenceNumber: $state['reference'] ?? (string) random_int(100000000, 999999999),
            traceNumber: (string) random_int(100000, 999999),
            cardMask: '603799******0000',
            raw: ['outcome' => 'success'],
        );
    }

    /** Record the customer's choice on the fake PSP page. */
    public static function complete(string $token, bool $success): ?array
    {
        $state = Cache::get(self::CACHE_PREFIX.$token);

        if (! is_array($state)) {
            return null;
        }

        $state['outcome'] = $success ? 'success' : 'failed';
        $state['reference'] = (string) random_int(100000000, 999999999);
        Cache::put(self::CACHE_PREFIX.$token, $state, now()->addHours(2));

        return $state;
    }
}
