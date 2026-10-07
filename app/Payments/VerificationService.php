<?php

namespace App\Payments;

use App\Enums\AttemptStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Gateways\Contracts\SupportsSettlement;
use App\Gateways\Data\GatewayVerifyResult;
use App\Gateways\GatewayManager;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Support\SensitiveData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles PSP callbacks and verification.
 *
 * Concurrency model: a caller must first *claim* verification by moving the payment to
 * `verifying` under a row lock. Only the claimer calls the PSP; any concurrent callback
 * sees `verifying` (or a final state) and backs off. The PSP call happens with no locks
 * held, and the result is written in a second locked transaction that re-checks the state.
 */
class VerificationService
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PaymentStateMachine $stateMachine,
        private readonly PaymentEventRecorder $events,
    ) {}

    /**
     * @param  array<string, mixed>  $callbackData  raw query/form fields from the PSP redirect
     */
    public function handleCallback(string $providerCode, Payment $payment, array $callbackData): Payment
    {
        $attempt = $payment->latestAttempt;
        $masked = SensitiveData::mask($callbackData);

        $this->events->record($payment, 'callback.received', 'callback', [
            'provider' => $providerCode,
            'fields' => $masked,
        ], $attempt);

        if ($attempt === null || $attempt->provider?->code !== $providerCode) {
            $this->events->record($payment, 'callback.rejected', 'callback', ['reason' => 'provider_mismatch', 'provider' => $providerCode], $attempt);

            return $payment;
        }

        $adapter = $this->gateways->for($providerCode);

        if (! $adapter->callbackMatchesAttempt($attempt, $callbackData)) {
            $this->events->record($payment, 'callback.rejected', 'callback', ['reason' => 'reference_mismatch'], $attempt);

            return $payment;
        }

        $claimed = DB::transaction(function () use ($payment, $attempt, $masked) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Redirected], true)) {
                $attempt->forceFill(['status' => AttemptStatus::CallbackReceived, 'callback_payload' => $masked])->save();
                $this->stateMachine->transition($locked, PaymentStatus::CallbackReceived, 'callback', attempt: $attempt);
            }

            return $this->claim($locked, $attempt, 'callback');
        });

        if (! $claimed) {
            return $payment->refresh();
        }

        // The raw (unmasked) callback is used for this verification only and never stored.
        return $this->verifyClaimed($payment, $attempt, $callbackData, 'callback');
    }

    /**
     * Verify on demand (client API / reconciliation). Uses the stored callback payload.
     * No-op for payments that are already final or have not received a callback.
     */
    public function verify(Payment $payment, string $source): Payment
    {
        $attempt = $payment->latestAttempt;

        if ($attempt === null) {
            return $payment;
        }

        $claimed = DB::transaction(fn () => $this->claim(
            Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail(),
            $attempt,
            $source,
        ));

        if (! $claimed) {
            return $payment->refresh();
        }

        return $this->verifyClaimed($payment, $attempt->refresh(), $attempt->callback_payload ?? [], $source);
    }

    /** Must run inside a transaction holding the payment row lock. */
    private function claim(Payment $locked, PaymentAttempt $attempt, string $source): bool
    {
        if ($locked->status === PaymentStatus::Verifying && $this->isStale($locked)) {
            $this->stateMachine->transition($locked, PaymentStatus::CallbackReceived, $source, [], ['reason' => 'stale_verification'], $attempt);
        }

        if ($locked->status !== PaymentStatus::CallbackReceived) {
            $this->events->record($locked, 'verification.skipped', $source, ['status' => $locked->status->value], $attempt);

            return false;
        }

        $this->stateMachine->transition($locked, PaymentStatus::Verifying, $source, attempt: $attempt);
        $this->events->record($locked, 'verification.requested', $source, [], $attempt);

        return true;
    }

    private function verifyClaimed(Payment $payment, PaymentAttempt $attempt, array $callbackData, string $source): Payment
    {
        $payment->refresh();
        $merchant = $attempt->merchant;
        $adapter = $this->gateways->for($attempt->provider);
        $transientError = null;

        try {
            $result = $adapter->verifyPayment($payment, $merchant, $attempt, $callbackData);
        } catch (GatewayException $e) {
            $result = null;
            $transientError = $e->getMessage();
        } catch (Throwable $e) {
            Log::error('gateway.verify_failed', ['payment_id' => $payment->public_id, 'exception' => $e::class]);
            $result = null;
            $transientError = 'Unexpected error during verification.';
        }

        $payment = DB::transaction(function () use ($payment, $attempt, $result, $transientError, $source) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Verifying) {
                // Another worker already finalized it (only possible after a stale reclaim).
                $this->events->record($locked, 'verification.discarded', $source, ['status' => $locked->status->value], $attempt);

                return $locked;
            }

            if ($result === null) {
                $this->events->record($locked, 'verification.error', $source, ['error' => $transientError], $attempt);

                return $this->stateMachine->transition($locked, PaymentStatus::CallbackReceived, $source, [], ['reason' => 'verification_error'], $attempt);
            }

            return $this->applyResult($locked, $attempt, $result, $source);
        });

        if ($payment->status === PaymentStatus::Paid) {
            $this->settle($payment, $attempt->refresh());
        }

        return $payment->refresh();
    }

    private function applyResult(Payment $locked, PaymentAttempt $attempt, GatewayVerifyResult $result, string $source): Payment
    {
        $attempt->forceFill([
            'status' => $result->verified ? AttemptStatus::Verified : AttemptStatus::Failed,
            'verify_payload' => ['raw' => SensitiveData::mask($result->raw), 'extra' => $result->extra],
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage ? mb_substr($result->errorMessage, 0, 1000) : null,
        ])->save();

        if ($result->verified) {
            $this->events->record($locked, 'verification.success', $source, ['reference_number' => $result->referenceNumber], $attempt);

            return $this->stateMachine->transition($locked, PaymentStatus::Paid, $source, [
                'reference_number' => $result->referenceNumber,
                'trace_number' => $result->traceNumber,
                'card_mask' => $result->cardMask,
                'paid_at' => now(),
            ], attempt: $attempt);
        }

        $this->events->record($locked, 'verification.failed', $source, [
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
        ], $attempt);

        return $this->stateMachine->transition($locked, PaymentStatus::Failed, $source, [], [
            'error_code' => $result->errorCode,
        ], $attempt);
    }

    /** Settle verified payments for PSPs that require it. Failure never un-pays a payment. */
    public function settle(Payment $payment, PaymentAttempt $attempt): void
    {
        $adapter = $this->gateways->for($attempt->provider);

        if (! $adapter instanceof SupportsSettlement || $payment->settled_at !== null) {
            return;
        }

        try {
            $result = $adapter->settlePayment($payment, $attempt->merchant, $attempt);
        } catch (Throwable $e) {
            $this->events->record($payment, 'settlement.failed', 'system', ['error' => $e->getMessage()], $attempt);

            return;
        }

        DB::transaction(function () use ($payment, $attempt, $result) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($result->successful && $locked->settled_at === null) {
                $locked->forceFill(['settled_at' => now()])->save();
            }

            $this->events->record($locked, $result->successful ? 'settlement.succeeded' : 'settlement.failed', 'system', [
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
            ], $attempt);
        });
    }

    private function isStale(Payment $payment): bool
    {
        return $payment->updated_at !== null
            && $payment->updated_at->lt(now()->subSeconds((int) config('payments.stale_verification_seconds')));
    }
}
