<?php

namespace App\Payments;

use App\Enums\AttemptStatus;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Exceptions\GatewayException;
use App\Gateways\Data\GatewayCreateResult;
use App\Gateways\GatewayManager;
use App\Models\Client;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Support\Mobile;
use App\Support\SensitiveData;
use App\Webhooks\WebhookService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Payment creation, new attempts and cancellation. Never marks a payment as paid -
 * only VerificationService does that, after the PSP confirms.
 */
class PaymentService
{
    public function __construct(
        private readonly MerchantResolver $merchants,
        private readonly GatewayManager $gateways,
        private readonly PaymentStateMachine $stateMachine,
        private readonly PaymentEventRecorder $events,
    ) {}

    /**
     * @param  array{order_id: string, amount: int, currency?: string, description?: ?string, return_url?: ?string, merchant_id?: ?string, metadata?: ?array, new_attempt?: bool}  $data
     * @return array{0: Payment, 1: bool} payment and whether a new payment/attempt was started
     */
    public function create(Client $client, array $data, ?string $idempotencyKey = null): array
    {
        $data['currency'] ??= config('payments.default_currency');
        $hash = $this->requestHash($data);

        if ($idempotencyKey !== null) {
            $existing = $client->payments()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    throw new ApiException('IDEMPOTENCY_KEY_REUSED', 'This Idempotency-Key was already used with a different request.', 422);
                }

                return [$existing, false];
            }
        }

        $existing = $client->payments()->where('order_id', $data['order_id'])->first();

        if ($existing !== null) {
            return $this->handleExistingOrder($client, $existing, $data, $hash);
        }

        $merchant = $this->merchants->resolve($client, $data['merchant_id'] ?? null);

        try {
            [$payment, $attempt] = DB::transaction(fn () => $this->insertPayment($client, $merchant, $data, $hash, $idempotencyKey));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request created the same order / idempotency key first.
            $existing = $client->payments()
                ->where(fn ($q) => $q->where('order_id', $data['order_id'])
                    ->when($idempotencyKey !== null, fn ($q) => $q->orWhere('idempotency_key', $idempotencyKey)))
                ->firstOrFail();

            if (! hash_equals((string) $existing->request_hash, $hash)) {
                throw new ApiException('ORDER_ALREADY_EXISTS', 'A payment for this order_id already exists with different parameters.', 409);
            }

            return [$existing, false];
        }

        $this->requestGatewayToken($payment, $attempt, $merchant);

        return [$payment->refresh(), true];
    }

    /** @return array{0: Payment, 1: bool} */
    private function handleExistingOrder(Client $client, Payment $existing, array $data, string $hash): array
    {
        if (! empty($data['new_attempt'])) {
            if ($existing->amount !== (int) $data['amount'] || $existing->currency->value !== $data['currency']) {
                throw new ApiException('ORDER_ALREADY_EXISTS', 'A new attempt must use the same amount and currency.', 409);
            }

            return [$this->newAttempt($client, $existing, $data['merchant_id'] ?? null), true];
        }

        if (! hash_equals((string) $existing->request_hash, $hash)) {
            throw new ApiException('ORDER_ALREADY_EXISTS', 'A payment for this order_id already exists with different parameters.', 409);
        }

        return [$existing, false];
    }

    /** Start a new PSP attempt on a failed payment (explicitly requested by the client). */
    public function newAttempt(Client $client, Payment $payment, ?string $merchantPublicId): Payment
    {
        $merchant = $this->merchants->resolve($client, $merchantPublicId);

        $attempt = DB::transaction(function () use ($payment, $merchant) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Failed) {
                throw new ApiException(
                    'NEW_ATTEMPT_NOT_ALLOWED',
                    "A new attempt is only allowed for failed payments (current status: {$locked->status->value}).",
                    409,
                );
            }

            if ($locked->latestAttempt?->status === AttemptStatus::Created) {
                throw new ApiException('ATTEMPT_IN_PROGRESS', 'Another attempt is already being started for this payment.', 409);
            }

            $locked->forceFill([
                'merchant_id' => $merchant->id,
                'provider_id' => $merchant->provider_id,
                'authority' => null,
                'token' => null,
                'expires_at' => now()->addMinutes((int) config('payments.payment_ttl_minutes')),
            ])->save();

            return $this->insertAttempt($locked, $merchant);
        });

        $this->requestGatewayToken($payment->refresh(), $attempt, $merchant);

        return $payment->refresh();
    }

    public function cancel(Payment $payment, string $source): Payment
    {
        return DB::transaction(function () use ($payment, $source) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PaymentStatus::Created, PaymentStatus::Pending, PaymentStatus::Redirected], true)) {
                throw new ApiException('PAYMENT_NOT_CANCELLABLE', "Payment in status {$locked->status->value} cannot be cancelled.", 409);
            }

            return $this->stateMachine->transition($locked, PaymentStatus::Cancelled, $source);
        });
    }

    /** @return array{0: Payment, 1: PaymentAttempt} */
    private function insertPayment(Client $client, Merchant $merchant, array $data, string $hash, ?string $idempotencyKey): array
    {
        $payment = new Payment([
            'client_id' => $client->id,
            'merchant_id' => $merchant->id,
            'provider_id' => $merchant->provider_id,
            'order_id' => $data['order_id'],
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $hash,
            'amount' => (int) $data['amount'],
            'currency' => Currency::from($data['currency']),
            'description' => $data['description'] ?? null,
            'customer_mobile' => Mobile::normalize($data['customer']['mobile'] ?? null),
            'customer_username' => isset($data['customer']['username']) ? trim((string) $data['customer']['username']) ?: null : null,
            'customer_name' => isset($data['customer']['name']) ? trim((string) $data['customer']['name']) ?: null : null,
            'return_url' => $data['return_url'] ?? $client->return_url,
            'metadata' => $data['metadata'] ?? null,
            'expires_at' => now()->addMinutes((int) config('payments.payment_ttl_minutes')),
        ]);
        $payment->status = PaymentStatus::Created;
        $payment->save();

        $payment->forceFill([
            'payment_url' => route('pay.show', ['payment' => $payment->public_id]),
            'callback_url' => route('gateways.callback', ['provider' => $merchant->provider->code, 'payment' => $payment->public_id]),
        ])->save();
        $payment->setRelation('client', $client);

        $this->events->record($payment, 'payment.created', 'api', [
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency->value,
            'merchant_id' => $merchant->public_id,
            'provider' => $merchant->provider->code,
        ], newStatus: PaymentStatus::Created->value);

        app(WebhookService::class)->enqueue($payment, 'payment.created');

        return [$payment, $this->insertAttempt($payment, $merchant)];
    }

    private function insertAttempt(Payment $payment, Merchant $merchant): PaymentAttempt
    {
        $number = $payment->attempts_count + 1;
        $payment->forceFill(['attempts_count' => $number])->save();

        for ($try = 0; ; $try++) {
            try {
                return DB::transaction(fn () => PaymentAttempt::create([
                    'payment_id' => $payment->id,
                    'merchant_id' => $merchant->id,
                    'provider_id' => $merchant->provider_id,
                    'attempt_number' => $number,
                    'status' => AttemptStatus::Created,
                    'psp_invoice_id' => random_int(100_000_000_000_000, 999_999_999_999_999),
                ]));
            } catch (UniqueConstraintViolationException $e) {
                // psp_invoice_id collision (astronomically rare): pick another number.
                if ($try >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Call the PSP outside of any DB transaction (no locks held during network I/O),
     * then persist the outcome atomically.
     */
    private function requestGatewayToken(Payment $payment, PaymentAttempt $attempt, Merchant $merchant): void
    {
        $adapter = $this->gateways->for($merchant->provider);

        $this->events->record($payment, 'gateway.requested', 'system', ['provider' => $merchant->provider->code], $attempt);

        try {
            $result = $adapter->createPayment($payment, $merchant, $attempt);
        } catch (GatewayException $e) {
            $result = GatewayCreateResult::failure('GATEWAY_UNAVAILABLE', $e->getMessage());
        } catch (ApiException $e) {
            $result = GatewayCreateResult::failure($e->errorCode, $e->getMessage());
        } catch (Throwable $e) {
            Log::error('gateway.create_failed', ['provider' => $merchant->provider->code, 'exception' => $e::class]);
            $result = GatewayCreateResult::failure('GATEWAY_ERROR', 'Unexpected error while contacting the payment provider.');
        }

        DB::transaction(function () use ($payment, $attempt, $result) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $attempt->forceFill([
                'status' => $result->successful ? AttemptStatus::Requested : AttemptStatus::Failed,
                'authority' => $result->authority,
                'token' => $result->token,
                'redirect_payload' => $result->redirect?->toArray(),
                'request_payload' => SensitiveData::mask($result->request) ?: null,
                'response_payload' => SensitiveData::mask($result->response) ?: null,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage ? mb_substr($result->errorMessage, 0, 1000) : null,
            ])->save();

            $this->events->record($locked, 'gateway.response_received', 'system', [
                'successful' => $result->successful,
                'error_code' => $result->errorCode,
            ], $attempt);

            if ($result->successful) {
                $this->stateMachine->transition($locked, PaymentStatus::Pending, 'system', [
                    'authority' => $result->authority,
                    'token' => $result->token,
                ], attempt: $attempt);
            } elseif ($locked->status === PaymentStatus::Created) {
                $this->stateMachine->transition($locked, PaymentStatus::Failed, 'system', [], [
                    'error_code' => $result->errorCode,
                    'error_message' => $result->errorMessage,
                ], $attempt);
            }
        });
    }

    private function requestHash(array $data): string
    {
        $normalized = [
            'order_id' => (string) $data['order_id'],
            'amount' => (int) $data['amount'],
            'currency' => (string) $data['currency'],
            'description' => $data['description'] ?? null,
            'return_url' => $data['return_url'] ?? null,
            'merchant_id' => $data['merchant_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ];

        // Payer details are only included when sent, so older integrations keep the same hash.
        if (! empty($data['customer'])) {
            $normalized['customer'] = $data['customer'];
        }

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
