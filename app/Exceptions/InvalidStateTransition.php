<?php

namespace App\Exceptions;

use App\Enums\PaymentStatus;

class InvalidStateTransition extends ApiException
{
    public function __construct(public readonly PaymentStatus $from, public readonly PaymentStatus $to)
    {
        parent::__construct(
            'INVALID_STATE_TRANSITION',
            "Payment cannot move from {$from->value} to {$to->value}.",
            409,
        );
    }
}
