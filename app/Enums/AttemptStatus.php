<?php

namespace App\Enums;

enum AttemptStatus: string
{
    case Created = 'created';
    case Requested = 'requested';
    case Redirected = 'redirected';
    case CallbackReceived = 'callback_received';
    case Verified = 'verified';
    case Failed = 'failed';
    case Superseded = 'superseded';
}
