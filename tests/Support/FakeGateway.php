<?php

namespace Tests\Support;

use App\Gateways\Contracts\GatewayInterface;
use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\Data\RedirectInstruction;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Closure;

/**
 * Scriptable adapter for tests. Registered under an existing provider code via
 * GatewayManager::extend().
 */
class FakeGateway implements GatewayInterface
{
    public int $verifyCalls = 0;

    public int $createCalls = 0;

    /** @var (Closure(Payment, PaymentAttempt, array): GatewayVerifyResult)|null */
    public ?Closure $onVerify = null;

    public ?Closure $onCreate = null;

    public function __construct(private readonly string $code = 'sandbox') {}

    public function code(): string
    {
        return $this->code;
    }

    public function requiredCredentials(): array
    {
        return [];
    }

    public function createPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewayCreateResult
    {
        $this->createCalls++;

        if ($this->onCreate) {
            return ($this->onCreate)($payment, $attempt);
        }

        return GatewayCreateResult::success(
            new RedirectInstruction('https://psp.example.com/pay', 'POST', ['token' => 'tok-'.$attempt->attempt_number]),
            authority: 'auth-'.$attempt->psp_invoice_id,
            token: 'tok-'.$attempt->attempt_number,
        );
    }

    public function callbackMatchesAttempt(PaymentAttempt $attempt, array $callbackData): bool
    {
        return ($callbackData['authority'] ?? null) === $attempt->authority;
    }

    public function verifyPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, array $callbackData): GatewayVerifyResult
    {
        $this->verifyCalls++;

        if ($this->onVerify) {
            return ($this->onVerify)($payment, $attempt, $callbackData);
        }

        return GatewayVerifyResult::verified('REF-'.$attempt->psp_invoice_id, 'TRACE-1', '603799******1234');
    }
}
