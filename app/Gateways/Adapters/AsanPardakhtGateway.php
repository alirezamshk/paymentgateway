<?php

namespace App\Gateways\Adapters;

use App\Enums\Currency;
use App\Gateways\Contracts\SupportsSettlement;
use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\Data\GatewaySettleResult;
use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\Data\RedirectInstruction;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Support\SensitiveData;

/**
 * Asan Pardakht IPG REST v1. Authentication via `usr` / `pwd` headers.
 *
 *   token:      POST {base}/v1/Token        -> 200 + token string
 *   pay:        POST {pay_url}  field RefId=token
 *   return:     customer is sent back to callbackURL (we identify the payment by URL)
 *   result:     GET  {base}/v1/TranResult?merchantConfigurationId=&localInvoiceId=  -> 200 + transaction
 *   verify:     POST {base}/v1/Verify      {merchantConfigurationId, payGateTranId} -> 200
 *   settlement: POST {base}/v1/Settlement  {merchantConfigurationId, payGateTranId} -> 200
 *
 * Amounts are in Rials. Credentials: merchant_identifier = merchantConfigurationId, username, password.
 * Provider config overrides: base_url, pay_url. Endpoints must be confirmed in Asan Pardakht's
 * test environment before production use.
 */
class AsanPardakhtGateway extends AbstractGateway implements SupportsSettlement
{
    public function code(): string
    {
        return 'asanpardakht';
    }

    public function requiredCredentials(): array
    {
        return [
            'merchant_identifier' => 'merchantConfigurationId',
            'username' => 'API username (usr header)',
            'password' => 'API password (pwd header)',
        ];
    }

    public function createPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewayCreateResult
    {
        $request = [
            'serviceTypeId' => 1,
            'merchantConfigurationId' => (int) $this->requireCredential($merchant, 'merchant_identifier'),
            'localInvoiceId' => $attempt->psp_invoice_id,
            'amountInRials' => $this->amountIn($payment, Currency::IRR),
            'localDate' => now('Asia/Tehran')->format('Ymd His'),
            'additionalData' => '',
            'callbackURL' => $this->callbackUrl($payment),
            'paymentId' => '0',
        ];

        $response = $this->send(fn ($http) => $this->authed($http, $merchant)->asJson()->post($this->baseUrl().'/v1/Token', $request));
        $token = trim((string) $response->body(), "\" \n\r\t");

        if ($response->status() === 200 && $token !== '' && ! str_starts_with($token, '{')) {
            return GatewayCreateResult::success(
                new RedirectInstruction($this->payUrl(), 'POST', ['RefId' => $token]),
                authority: null,
                token: $token,
                request: $request,
                response: ['status' => 200, 'token' => $token],
            );
        }

        return GatewayCreateResult::failure(
            'ASANPARDAKHT_'.$response->status(),
            'Asan Pardakht token request failed',
            $request,
            ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)],
        );
    }

    public function callbackMatchesAttempt(PaymentAttempt $attempt, array $callbackData): bool
    {
        // Asan Pardakht does not echo our token reliably; the result is looked up by
        // localInvoiceId (psp_invoice_id) during verification, which binds it to this attempt.
        return $attempt->token !== null;
    }

    public function verifyPayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt, array $callbackData): GatewayVerifyResult
    {
        $configId = (int) $this->requireCredential($merchant, 'merchant_identifier');
        $query = ['merchantConfigurationId' => $configId, 'localInvoiceId' => $attempt->psp_invoice_id];

        $result = $this->send(fn ($http) => $this->authed($http, $merchant)->get($this->baseUrl().'/v1/TranResult', $query));
        $tran = $this->json($result);
        $raw = ['tran_result' => ['status' => $result->status(), 'body' => $tran]];

        if ($result->status() !== 200 || empty($tran['payGateTranID'])) {
            return GatewayVerifyResult::rejected('ASANPARDAKHT_TRANRESULT_'.$result->status(), 'No successful transaction found at PSP.', $raw);
        }

        if ((int) ($tran['amount'] ?? -1) !== $this->amountIn($payment, Currency::IRR)) {
            return GatewayVerifyResult::rejected('AMOUNT_MISMATCH', 'PSP transaction amount differs from payment amount.', $raw);
        }

        $body = ['merchantConfigurationId' => $configId, 'payGateTranId' => (int) $tran['payGateTranID']];
        $verify = $this->send(fn ($http) => $this->authed($http, $merchant)->asJson()->post($this->baseUrl().'/v1/Verify', $body));
        $raw['verify'] = ['request' => $body, 'status' => $verify->status()];

        if ($verify->status() !== 200) {
            return GatewayVerifyResult::rejected('ASANPARDAKHT_VERIFY_'.$verify->status(), 'Asan Pardakht verify failed.', $raw);
        }

        return GatewayVerifyResult::verified(
            referenceNumber: (string) ($tran['rrn'] ?? $tran['refID'] ?? $tran['payGateTranID']),
            traceNumber: isset($tran['refID']) ? (string) $tran['refID'] : null,
            cardMask: SensitiveData::maskPan($tran['cardNumber'] ?? null),
            raw: $raw,
            extra: ['payGateTranId' => (int) $tran['payGateTranID']],
        );
    }

    public function settlePayment(Payment $payment, Merchant $merchant, PaymentAttempt $attempt): GatewaySettleResult
    {
        $payGateTranId = (int) data_get($attempt->verify_payload, 'extra.payGateTranId');

        if ($payGateTranId <= 0) {
            return new GatewaySettleResult(false, 'MISSING_PAYGATE_TRAN_ID', 'Verified transaction id is not available.');
        }

        $body = [
            'merchantConfigurationId' => (int) $this->requireCredential($merchant, 'merchant_identifier'),
            'payGateTranId' => $payGateTranId,
        ];
        $response = $this->send(fn ($http) => $this->authed($http, $merchant)->asJson()->post($this->baseUrl().'/v1/Settlement', $body));

        return $response->status() === 200
            ? new GatewaySettleResult(true, raw: ['status' => 200])
            : new GatewaySettleResult(false, 'ASANPARDAKHT_SETTLEMENT_'.$response->status(), 'Settlement failed.', ['status' => $response->status()]);
    }

    private function authed($http, Merchant $merchant)
    {
        return $http->withHeaders([
            'usr' => $this->requireCredential($merchant, 'username'),
            'pwd' => $this->requireCredential($merchant, 'password'),
        ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->setting('base_url', 'https://ipgrest.asanpardakht.ir'), '/');
    }

    private function payUrl(): string
    {
        return (string) $this->setting('pay_url', 'https://asan.shaparak.ir');
    }
}
