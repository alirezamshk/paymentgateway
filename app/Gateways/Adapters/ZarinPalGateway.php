<?php

namespace App\Gateways\Adapters;

use App\Gateways\Contracts\SupportsCredentialCheck;
use App\Gateways\Data\GatewayCheckResult;
use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\Data\RedirectInstruction;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Support\SensitiveData;

/**
 * ZarinPal REST API v4.
 *
 *   request: POST {base}/pg/v4/payment/request.json -> data.code 100 + data.authority
 *   pay:     GET  {base}/pg/StartPay/{authority}
 *   return:  GET  callback?Authority=...&Status=OK|NOK
 *   verify:  POST {base}/pg/v4/payment/verify.json  -> data.code 100 (verified) / 101 (already verified)
 *
 * Provider config: {"sandbox": true} switches to sandbox.zarinpal.com; {"base_url": "..."} overrides.
 * Credentials: merchant_identifier = 36 character ZarinPal merchant_id.
 * ZarinPal accepts both IRR and IRT, so the payment currency is passed through unchanged.
 */
class ZarinPalGateway extends AbstractGateway implements SupportsCredentialCheck
{
    public function code(): string
    {
        return 'zarinpal';
    }

    public function requiredCredentials(): array
    {
        return ['merchant_identifier' => 'ZarinPal merchant_id (UUID)'];
    }

    public function createPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewayCreateResult
    {
        // Sandbox "payments" move no money: in production they are allowed for admin tests only.
        if ($this->setting('sandbox', false) && app()->isProduction() && ! $payment->is_test) {
            return GatewayCreateResult::failure('SANDBOX_NOT_ALLOWED', 'ZarinPal is in sandbox mode; only admin test payments may use it in production.');
        }

        $request = [
            'merchant_id' => $this->requireCredential($merchant, 'merchant_identifier'),
            'amount' => $payment->amount,
            'currency' => $payment->currency->value,
            'callback_url' => $this->callbackUrl($payment),
            'description' => $payment->description ?: "Order {$payment->order_id}",
            'metadata' => ['order_id' => (string) $attempt->psp_invoice_id],
        ];

        $response = $this->send(fn ($http) => $http->asJson()->post($this->baseUrl().'/pg/v4/payment/request.json', $request));
        $body = $this->json($response);
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        if ((int) ($data['code'] ?? 0) === 100 && ! empty($data['authority'])) {
            $authority = (string) $data['authority'];

            return GatewayCreateResult::success(
                new RedirectInstruction($this->baseUrl().'/pg/StartPay/'.rawurlencode($authority)),
                authority: $authority,
                token: null,
                request: $request,
                response: $body,
            );
        }

        [$code, $message] = $this->error($body);

        return GatewayCreateResult::failure($code, $message, $request, $body);
    }

    public function callbackMatchesAttempt(PaymentAttempt $attempt, array $callbackData): bool
    {
        $authority = (string) ($callbackData['Authority'] ?? '');

        return $authority !== '' && $attempt->authority !== null && hash_equals($attempt->authority, $authority);
    }

    public function verifyPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, array $callbackData): GatewayVerifyResult
    {
        // Status=NOK is only a hint; the verify call below is the authoritative answer either way.
        $request = [
            'merchant_id' => $this->requireCredential($merchant, 'merchant_identifier'),
            'amount' => $payment->amount,
            'authority' => (string) $attempt->authority,
        ];

        $response = $this->send(fn ($http) => $http->asJson()->post($this->baseUrl().'/pg/v4/payment/verify.json', $request));
        $body = $this->json($response);
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $raw = ['request' => $request, 'response' => $body];

        if (in_array((int) ($data['code'] ?? 0), [100, 101], true) && ! empty($data['ref_id'])) {
            return GatewayVerifyResult::verified(
                referenceNumber: (string) $data['ref_id'],
                traceNumber: null,
                cardMask: SensitiveData::maskPan($data['card_pan'] ?? null),
                raw: $raw,
            );
        }

        [$code, $message] = $this->error($body);

        return GatewayVerifyResult::rejected($code, $message, $raw);
    }

    public function checkCredentials(Merchant $merchant): GatewayCheckResult
    {
        $merchantId = (string) $merchant->credential('merchant_identifier', '');

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $merchantId)) {
            return new GatewayCheckResult(false, 'merchant_identifier must be a 36 character ZarinPal merchant_id.');
        }

        return new GatewayCheckResult(true, 'Credential format is valid. Run a sandbox payment to confirm it end-to-end.');
    }

    private function baseUrl(): string
    {
        $default = $this->setting('sandbox', false) ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';

        return rtrim((string) $this->setting('base_url', $default), '/');
    }

    /** @return array{0: string, 1: string} */
    private function error(array $body): array
    {
        $errors = $body['errors'] ?? [];
        $data = $body['data'] ?? [];
        $code = is_array($errors) && isset($errors['code']) ? $errors['code'] : ($data['code'] ?? 'unknown');
        $message = is_array($errors) && isset($errors['message']) ? $errors['message'] : ($data['message'] ?? 'ZarinPal request failed');

        return ['ZARINPAL_'.$code, (string) $message];
    }
}
