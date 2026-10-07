<?php

namespace App\Gateways\Adapters;

use App\Enums\Currency;
use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\Data\RedirectInstruction;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Support\SensitiveData;

/**
 * Sepordeh invoice API.
 *
 *   add:    POST {base}/merchant/invoices/add     {merchant, amount, callback, orderId, description}
 *           -> {status: 200, information: {invoice_id}}
 *   pay:    GET  {base}/merchant/invoices/pay/id:{invoice_id}
 *   return: GET  callback?authority={invoice_id}
 *   verify: POST {base}/merchant/invoices/verify  {merchant, authority} -> {status: 200, information: {...}}
 *
 * Credentials: merchant_identifier = Sepordeh merchant key.
 * Provider config: base_url, amount_currency ("IRT" by default - Sepordeh amounts are in Tomans;
 * IRR payments are converted exactly or rejected). Confirm these against Sepordeh's current
 * documentation and sandbox before production use.
 */
class SepordehGateway extends AbstractGateway
{
    public function code(): string
    {
        return 'sepordeh';
    }

    public function requiredCredentials(): array
    {
        return ['merchant_identifier' => 'Sepordeh merchant key'];
    }

    public function createPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewayCreateResult
    {
        $request = [
            'merchant' => $this->requireCredential($merchant, 'merchant_identifier'),
            'amount' => $this->amountIn($payment, $this->pspCurrency()),
            'callback' => $this->callbackUrl($payment),
            'orderId' => (string) $attempt->psp_invoice_id,
            'description' => $payment->description ?: "Order {$payment->order_id}",
        ];

        $response = $this->send(fn ($http) => $http->asForm()->post($this->baseUrl().'/merchant/invoices/add', $request));
        $body = $this->json($response);
        $invoiceId = data_get($body, 'information.invoice_id');

        if ((int) ($body['status'] ?? 0) === 200 && ! empty($invoiceId)) {
            return GatewayCreateResult::success(
                new RedirectInstruction($this->baseUrl().'/merchant/invoices/pay/id:'.rawurlencode((string) $invoiceId)),
                authority: (string) $invoiceId,
                token: null,
                request: $request,
                response: $body,
            );
        }

        return GatewayCreateResult::failure(
            'SEPORDEH_'.($body['status'] ?? $response->status()),
            (string) ($body['message'] ?? 'Sepordeh invoice request failed'),
            $request,
            $body,
        );
    }

    public function callbackMatchesAttempt(PaymentAttempt $attempt, array $callbackData): bool
    {
        $authority = (string) ($callbackData['authority'] ?? '');

        return $authority !== '' && $attempt->authority !== null && hash_equals($attempt->authority, $authority);
    }

    public function verifyPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, array $callbackData): GatewayVerifyResult
    {
        $request = [
            'merchant' => $this->requireCredential($merchant, 'merchant_identifier'),
            'authority' => (string) $attempt->authority,
        ];

        $response = $this->send(fn ($http) => $http->asForm()->post($this->baseUrl().'/merchant/invoices/verify', $request));
        $body = $this->json($response);
        $raw = ['request' => $request, 'response' => $body];
        $info = is_array($body['information'] ?? null) ? $body['information'] : [];

        if ((int) ($body['status'] ?? 0) !== 200) {
            return GatewayVerifyResult::rejected('SEPORDEH_'.($body['status'] ?? $response->status()), (string) ($body['message'] ?? 'Sepordeh verify failed'), $raw);
        }

        if (isset($info['amount']) && (int) $info['amount'] !== $this->amountIn($payment, $this->pspCurrency())) {
            return GatewayVerifyResult::rejected('AMOUNT_MISMATCH', 'PSP transaction amount differs from payment amount.', $raw);
        }

        return GatewayVerifyResult::verified(
            referenceNumber: (string) ($info['refid'] ?? $info['ref_id'] ?? $info['invoice_id'] ?? $attempt->authority),
            traceNumber: null,
            cardMask: SensitiveData::maskPan($info['card'] ?? null),
            raw: $raw,
        );
    }

    private function pspCurrency(): Currency
    {
        return Currency::from((string) $this->setting('amount_currency', 'IRT'));
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->setting('base_url', 'https://sepordeh.com'), '/');
    }
}
