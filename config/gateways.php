<?php

use App\Gateways\Adapters\AsanPardakhtGateway;
use App\Gateways\Adapters\SandboxGateway;
use App\Gateways\Adapters\SepehrGateway;
use App\Gateways\Adapters\SepordehGateway;
use App\Gateways\Adapters\ZarinPalGateway;

/*
| Provider code => adapter class. Adding a PSP = add an adapter class + a line here
| + a gateway_providers row. The Payment API does not change.
|
| Endpoint defaults live in each adapter and can be overridden per provider through
| gateway_providers.config (e.g. {"sandbox": true} or {"base_url": "..."}).
*/
return [
    'adapters' => [
        'zarinpal' => ZarinPalGateway::class,
        'sepehr' => SepehrGateway::class,
        'asanpardakht' => AsanPardakhtGateway::class,
        'sepordeh' => SepordehGateway::class,
        // Internal fake PSP for automated tests and local development. Refused in production.
        'sandbox' => SandboxGateway::class,
    ],

    'http' => [
        'timeout' => (int) env('GATEWAY_HTTP_TIMEOUT', 20),
        'connect_timeout' => (int) env('GATEWAY_HTTP_CONNECT_TIMEOUT', 10),
    ],

    // Enables the internal sandbox provider and its fake payment page.
    'sandbox_enabled' => (bool) env('GATEWAY_SANDBOX_ENABLED', false),
];
