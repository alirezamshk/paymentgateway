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
 * Sepehr Electronic Payment (Bank Saderat / Mabna) IPG.
 *
 *   token:  POST {api}/V1/PeymentApi/GetToken  (form) Amount, callbackURL, InvoiceID, TerminalID, Payload
 *           -> {Status: 0, Accesstoken}
 *   pay:    POST {pay_url}  fields token, terminalID
 *   return: POST callback with respcode, respmsg, amount, invoiceid, terminalid, tracenumber, rrn,
 *           digitalreceipt, cardnumber (masked), ...  respcode 0 = customer completed payment.
 *   verify: POST {api}/V1/PeymentApi/Advice  (form) digitalreceipt, Tid
 *           -> {Status: "Ok"|"Duplicate"|"NOk", ReturnId}; ReturnId must equal the paid amount.
 *
 * Amounts are in Rials. Credentials: terminal_identifier = terminal id.
 * The server IP must be registered with Sepehr and outbound port 8081 must be open
 * (error -2 otherwise). Provider config overrides: api_url, pay_url.
 * Cross-checked against the shetabit/multipay Sepehr driver; confirm with a real payment
 * before production use.
 */
class SepehrGateway extends AbstractGateway
{
    /** GetToken / Advice error codes. */
    private const ERRORS = [
        '-1' => 'Transaction not found.',
        '-2' => 'IP mismatch or port 8081 is closed: register the server IP with Sepehr and open outbound port 8081.',
        '-3' => 'General PSP error.',
        '-4' => 'This request is not allowed for this transaction.',
        '-5' => 'Invalid IP address.',
        '-6' => 'Reversal service is not enabled for this merchant.',
    ];

    /** Callback respcode values. */
    private const RESPONSE_CODES = [
        '-1' => 'Transaction cancelled by the customer.',
        '-2' => 'Payment time expired for the customer.',
    ];

    public function code(): string
    {
        return 'sepehr';
    }

    public function requiredCredentials(): array
    {
        return ['terminal_identifier' => 'Sepehr terminal ID'];
    }

    public function createPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewayCreateResult
    {
        $terminalId = $this->requireCredential($merchant, 'terminal_identifier');
        $request = [
            'Amount' => $this->amountIn($payment, Currency::IRR),
            'callbackURL' => $this->callbackUrl($payment),
            'InvoiceID' => (string) $attempt->psp_invoice_id,
            'TerminalID' => $terminalId,
            'Payload' => '',
        ];

        $response = $this->send(fn ($http) => $http->asForm()->post($this->apiUrl().'/V1/PeymentApi/GetToken', $request));
        $body = $this->normalize($this->json($response));
        $status = (string) ($body['status'] ?? '');
        $token = (string) ($body['accesstoken'] ?? '');

        if ($response->successful() && $status === '0' && $token !== '') {
            return GatewayCreateResult::success(
                new RedirectInstruction($this->payUrl(), 'POST', ['token' => $token, 'terminalID' => $terminalId]),
                authority: null,
                token: $token,
                request: $request,
                response: $body,
            );
        }

        return GatewayCreateResult::failure(
            'SEPEHR_'.($status !== '' ? $status : $response->status()),
            self::ERRORS[$status] ?? (string) ($body['message'] ?? 'Sepehr token request failed'),
            $request,
            $body,
        );
    }

    public function callbackMatchesAttempt(PaymentAttempt $attempt, array $callbackData): bool
    {
        return (string) ($callbackData['invoiceid'] ?? '') === (string) $attempt->psp_invoice_id;
    }

    public function verifyPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, array $callbackData): GatewayVerifyResult
    {
        $terminalId = $this->requireCredential($merchant, 'terminal_identifier');
        $expectedAmount = $this->amountIn($payment, Currency::IRR);
        $respcode = (string) ($callbackData['respcode'] ?? '');
        $receipt = (string) ($callbackData['digitalreceipt'] ?? '');

        // Without a successful respcode there is no digital receipt to advise; nothing was captured.
        if ($respcode !== '0' || $receipt === '') {
            return GatewayVerifyResult::rejected(
                'SEPEHR_RESP_'.($respcode !== '' ? $respcode : 'missing'),
                self::RESPONSE_CODES[$respcode] ?? (string) ($callbackData['respmsg'] ?? 'Payment was not completed at the PSP.'),
            );
        }

        $request = ['digitalreceipt' => $receipt, 'Tid' => $terminalId];
        $response = $this->send(fn ($http) => $http->asForm()->post($this->apiUrl().'/V1/PeymentApi/Advice', $request));
        $body = $this->normalize($this->json($response));
        $raw = ['request' => $request, 'response' => $body];
        $status = strtolower((string) ($body['status'] ?? ''));

        if (in_array($status, ['ok', 'duplicate'], true)) {
            if ((int) ($body['returnid'] ?? -1) !== $expectedAmount) {
                return GatewayVerifyResult::rejected(
                    'AMOUNT_MISMATCH',
                    'PSP confirmed a different amount than the payment amount. Manual review required.',
                    $raw,
                );
            }

            return GatewayVerifyResult::verified(
                referenceNumber: (string) ($callbackData['rrn'] ?? $receipt),
                traceNumber: isset($callbackData['tracenumber']) ? (string) $callbackData['tracenumber'] : null,
                cardMask: SensitiveData::maskPan($callbackData['cardnumber'] ?? null),
                raw: $raw,
                extra: ['digitalreceipt' => $receipt],
            );
        }

        $returnId = (string) ($body['returnid'] ?? '');

        return GatewayVerifyResult::rejected(
            'SEPEHR_ADVICE_'.($returnId !== '' ? $returnId : ($status ?: 'unknown')),
            self::ERRORS[$returnId] ?? (string) ($body['message'] ?? 'Sepehr advice failed'),
            $raw,
        );
    }

    /** Lower-case all keys (Sepehr responses are not consistently cased, e.g. Accesstoken). */
    private function normalize(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $out[is_string($key) ? strtolower($key) : $key] = is_array($value) ? $this->normalize($value) : $value;
        }

        return $out;
    }

    private function apiUrl(): string
    {
        return rtrim((string) $this->setting('api_url', 'https://sepehr.shaparak.ir:8081'), '/');
    }

    private function payUrl(): string
    {
        return (string) $this->setting('pay_url', 'https://sepehr.shaparak.ir:8080/Pay');
    }
}
