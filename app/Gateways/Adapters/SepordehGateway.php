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
 *           or  {base}/merchant/invoices/pay/automatic:true/id:{invoice_id} ("direct" mode)
 *   return: GET  callback?authority={invoice_id}
 *   verify: POST {base}/merchant/invoices/verify  {merchant, authority} -> {status: 200, information: {...}}
 *
 * Credentials: merchant_identifier = Sepordeh merchant key.
 * Provider config: base_url, amount_currency ("IRT" by default - Sepordeh amounts are in Tomans;
 * IRR payments are converted exactly or rejected), direct (true = skip Sepordeh's own page and
 * go straight to the bank). Response keys are read case-insensitively.
 * Cross-checked against the shetabit/multipay Sepordeh driver; confirm with a real payment
 * before production use.
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
        $body = $this->normalize($this->json($response));
        $invoiceId = data_get($body, 'information.invoice_id');

        if ((int) ($body['status'] ?? 0) === 200 && ! empty($invoiceId)) {
            $payPath = $this->setting('direct', false) ? '/merchant/invoices/pay/automatic:true/id:' : '/merchant/invoices/pay/id:';

            return GatewayCreateResult::success(
                new RedirectInstruction($this->baseUrl().$payPath.rawurlencode((string) $invoiceId)),
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
        $body = $this->normalize($this->json($response));
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

    /** Lower-case all keys (Sepordeh responses are not consistently cased). */
    private function normalize(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $out[is_string($key) ? strtolower($key) : $key] = is_array($value) ? $this->normalize($value) : $value;
        }

        return $out;
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
