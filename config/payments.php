<?php

return [
    /*
    | Client HMAC authentication.
    */
    'auth' => [
        // Allowed clock skew (seconds) between X-Timestamp and server time.
        'timestamp_tolerance' => (int) env('PAYMENTS_AUTH_TIMESTAMP_TOLERANCE', 300),
        // Cache store used for nonce replay protection (use redis in production).
        'nonce_store' => env('PAYMENTS_NONCE_STORE'),
        // Max authenticated API requests per client per minute.
        'rate_limit_per_minute' => (int) env('PAYMENTS_RATE_LIMIT_PER_MINUTE', 120),
        // Max API requests per IP per minute (applied before authentication).
        'ip_rate_limit_per_minute' => (int) env('PAYMENTS_IP_RATE_LIMIT_PER_MINUTE', 300),
    ],

    /*
    | Amount limits per currency (integer, in that currency's unit).
    */
    'amount_limits' => [
        'IRR' => ['min' => 10_000, 'max' => 2_000_000_000_000],
        'IRT' => ['min' => 1_000, 'max' => 200_000_000_000],
    ],

    'default_currency' => env('PAYMENTS_DEFAULT_CURRENCY', 'IRR'),

    // Unpaid payments (created/pending/redirected) expire after this many minutes.
    'payment_ttl_minutes' => (int) env('PAYMENTS_TTL_MINUTES', 60),

    // A payment stuck in "verifying" longer than this is considered abandoned and re-verified.
    'stale_verification_seconds' => (int) env('PAYMENTS_STALE_VERIFICATION_SECONDS', 120),

    // Allow non-HTTPS return/webhook URLs (local development only).
    'allow_insecure_urls' => (bool) env('PAYMENTS_ALLOW_INSECURE_URLS', false),

    // Run the queue worker from the scheduler (cron) instead of a long-running process.
    // Use on shared hosting / cPanel where Supervisor is not available.
    'queue_via_scheduler' => (bool) env('QUEUE_WORK_VIA_SCHEDULER', false),

    'webhooks' => [
        'timeout' => (int) env('WEBHOOK_TIMEOUT', 10),
        'max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 8),
        // Backoff = base * 2^(attempt-1) seconds, capped.
        'backoff_base_seconds' => (int) env('WEBHOOK_BACKOFF_BASE', 30),
        'backoff_max_seconds' => (int) env('WEBHOOK_BACKOFF_MAX', 6 * 3600),
        'queue' => env('WEBHOOK_QUEUE', 'webhooks'),
        // Resolve the webhook host and refuse private/reserved IPs (SSRF protection).
        'block_private_networks' => (bool) env('WEBHOOK_BLOCK_PRIVATE_NETWORKS', true),
        // White-labelling: header names are {prefix}Event, {prefix}Delivery-Id,
        // {prefix}Timestamp and {prefix}Signature.
        'header_prefix' => env('WEBHOOK_HEADER_PREFIX', 'X-Webhook-'),
        'user_agent' => env('WEBHOOK_USER_AGENT', 'PaymentService-Webhooks/1.0'),
    ],
];
