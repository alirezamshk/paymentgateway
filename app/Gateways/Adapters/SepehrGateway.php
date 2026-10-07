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
 *   token:  POST {api}/V1/PeymentApi/GetToken  {Amount, callbackURL, invoiceID, terminalID, payload}
 *           -> {Status: 0, AccessToken}
 *   pay:    POST {pay_url}  fields TerminalID, token
 *   return: POST callback with respcode, respmsg, amount, invoiceid, terminalid, tracenumber, rrn,
 *           digitalreceipt, cardnumber (masked), ...  respcode 0 = customer completed payment.
 *   verify: POST {api}/V1/PeymentApi/Advice {digitalreceipt, Tid} -> {Status: "Ok"|"Duplicate"|"NOk", ReturnId}
 *           ReturnId must equal the paid amount.
 *
 * Amounts are in Rials. Credentials: terminal_identifier = terminal id.
 * Provider config overrides: api_url, pay_url. Endpoints must be confirmed against the
 * documentation that comes with your Sepehr contract before production use.
 */
class SepehrGateway extends AbstractGateway
{
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
            'invoiceID' => (string) $attempt->psp_invoice_id,
            'terminalID' => (int) $terminalId,
            'Payload' => '',
        ];

        $response = $this->send(fn ($http) => $http->asJson()->post($this->apiUrl().'/V1/PeymentApi/GetToken', $request));
        $body = $this->json($response);

        if ($response->successful() && (string) ($body['Status'] ?? '') === '0' && ! empty($body['AccessToken'])) {
            $token = (string) $body['AccessToken'];

            return GatewayCreateResult::success(
                new RedirectInstruction($this->payUrl(), 'POST', ['TerminalID' => $terminalId, 'token' => $token]),
                authority: null,
                token: $token,
                request: $request,
                response: $body,
            );
        }

        return GatewayCreateResult::failure(
            'SEPEHR_'.($body['Status'] ?? $response->status()),
            (string) ($body['Message'] ?? 'Sepehr token request failed'),
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
        $receipt = (string) ($callbackData['digitalreceipt'] ?? '');

        // Without a successful respcode there is no digital receipt to advise; nothing was captured.
        if ((string) ($callbackData['respcode'] ?? '') !== '0' || $receipt === '') {
            return GatewayVerifyResult::rejected(
                'SEPEHR_RESP_'.($callbackData['respcode'] ?? 'missing'),
                (string) ($callbackData['respmsg'] ?? 'Payment was not completed at the PSP.'),
            );
        }

        $request = ['digitalreceipt' => $receipt, 'Tid' => $terminalId];
        $response = $this->send(fn ($http) => $http->asJson()->post($this->apiUrl().'/V1/PeymentApi/Advice', $request));
        $body = $this->json($response);
        $raw = ['request' => $request, 'response' => $body];
        $status = strtolower((string) ($body['Status'] ?? ''));

        if (in_array($status, ['ok', 'duplicate'], true)) {
            if ((int) ($body['ReturnId'] ?? -1) !== $expectedAmount) {
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

        return GatewayVerifyResult::rejected(
            'SEPEHR_ADVICE_'.($body['ReturnId'] ?? $status ?: 'unknown'),
            (string) ($body['Message'] ?? 'Sepehr advice failed'),
            $raw,
        );
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
