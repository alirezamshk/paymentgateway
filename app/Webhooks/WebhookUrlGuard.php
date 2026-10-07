<?php

namespace App\Webhooks;

/**
 * Basic SSRF protection for outgoing webhook requests.
 */
final class WebhookUrlGuard
{
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }

        if (config('payments.allow_insecure_urls')) {
            return in_array($parts['scheme'], ['http', 'https'], true);
        }

        if ($parts['scheme'] !== 'https') {
            return false;
        }

        if (! config('payments.webhooks.block_private_networks')) {
            return true;
        }

        $ips = filter_var($parts['host'], FILTER_VALIDATE_IP) ? [$parts['host']] : (gethostbynamel($parts['host']) ?: []);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
