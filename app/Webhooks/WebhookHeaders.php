<?php

namespace App\Webhooks;

/**
 * Names of the webhook headers. The prefix is configurable (WEBHOOK_HEADER_PREFIX) so the
 * service can be white-labelled; the default is "X-Webhook-".
 */
final class WebhookHeaders
{
    public static function prefix(): string
    {
        $prefix = trim((string) config('payments.webhooks.header_prefix', 'X-Webhook-'));

        // Header names may only contain token characters; fall back to the default otherwise.
        if (! preg_match('/^[A-Za-z0-9-]+$/', $prefix)) {
            return 'X-Webhook-';
        }

        return str_ends_with($prefix, '-') ? $prefix : $prefix.'-';
    }

    public static function event(): string
    {
        return self::prefix().'Event';
    }

    public static function deliveryId(): string
    {
        return self::prefix().'Delivery-Id';
    }

    public static function timestamp(): string
    {
        return self::prefix().'Timestamp';
    }

    public static function signature(): string
    {
        return self::prefix().'Signature';
    }
}
