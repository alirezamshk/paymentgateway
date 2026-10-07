<?php

namespace App\Payments;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Support\RequestContext;
use App\Support\SensitiveData;

class PaymentEventRecorder
{
    /**
     * @param  array<string, mixed>  $metadata  masked before storage
     */
    public function record(
        Payment $payment,
        string $event,
        string $source,
        array $metadata = [],
        ?PaymentAttempt $attempt = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
    ): PaymentEvent {
        return PaymentEvent::create([
            'payment_id' => $payment->id,
            'payment_attempt_id' => $attempt?->id,
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'source' => $source,
            'request_id' => RequestContext::idOrNew(),
            'metadata' => SensitiveData::mask($metadata) ?: null,
        ]);
    }
}
