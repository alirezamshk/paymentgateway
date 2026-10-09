<?php

namespace App\Webhooks;

/**
 * SSRF protection for outgoing webhook requests: public HTTPS endpoints only. Every A and AAAA
 * record must be a globally routable address, and the delivery is pinned to the address that
 * was checked (no second DNS lookup an attacker could rebind).
 */
final class WebhookUrlGuard
{
    public static function isAllowed(string $url): bool
    {
        return self::resolve($url) !== null;
    }

    /**
     * @return array{host: string, port: int, ip: ?string}|null null when the URL is not allowed;
     *                                                          ip is null when no pinning applies
     */
    public static function resolve(string $url): ?array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(trim($parts['host'], '[]'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (config('payments.allow_insecure_urls')) {
            return in_array($scheme, ['http', 'https'], true) ? ['host' => $host, 'port' => $port, 'ip' => null] : null;
        }

        if ($scheme !== 'https') {
            return null;
        }

        if (! config('payments.webhooks.block_private_networks')) {
            return ['host' => $host, 'port' => $port, 'ip' => null];
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::lookup($host);

        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
                return null;
            }
        }

        // Prefer IPv4 for pinning; every address was checked above.
        $v4 = array_values(array_filter($ips, fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));

        return ['host' => $host, 'port' => $port, 'ip' => $v4[0] ?? $ips[0]];
    }

    /** @return list<string> */
    private static function lookup(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $ips = [];

        foreach ($records as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        $ips = array_values(array_unique(array_filter($ips)));

        return $ips !== [] ? $ips : (gethostbynamel($host) ?: []);
    }
}
