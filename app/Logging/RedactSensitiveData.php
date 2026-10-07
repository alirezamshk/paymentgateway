<?php

namespace App\Logging;

use App\Support\SensitiveData;
use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Monolog tap: masks secrets and card numbers in every log record's context and message.
 */
class RedactSensitiveData
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(function (LogRecord $record): LogRecord {
            return $record->with(
                message: SensitiveData::maskPansInText($record->message),
                context: SensitiveData::mask($record->context),
                extra: SensitiveData::mask($record->extra),
            );
        });
    }
}
