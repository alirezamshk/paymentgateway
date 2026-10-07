<?php

namespace App\Gateways;

use App\Exceptions\ApiException;
use App\Gateways\Contracts\GatewayInterface;
use App\Models\GatewayProvider;
use Illuminate\Contracts\Container\Container;

/**
 * Registry that maps a provider code to its adapter. Core payment code only talks
 * to GatewayInterface obtained from here.
 */
class GatewayManager
{
    /** @var array<string, GatewayInterface> */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    public function for(GatewayProvider|string $provider): GatewayInterface
    {
        $code = $provider instanceof GatewayProvider ? $provider->code : $provider;

        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $class = config("gateways.adapters.{$code}");

        if (! is_string($class) || ! class_exists($class)) {
            throw new ApiException('PROVIDER_NOT_SUPPORTED', "No adapter registered for provider [{$code}].", 422);
        }

        if ($code === 'sandbox' && ! $this->sandboxAllowed()) {
            throw new ApiException('PROVIDER_NOT_SUPPORTED', 'The sandbox provider is disabled.', 422);
        }

        $adapter = $this->container->make($class);

        if (! $adapter instanceof GatewayInterface) {
            throw new \LogicException("{$class} must implement ".GatewayInterface::class);
        }

        return $this->resolved[$code] = $adapter;
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, config('gateways.adapters', []))
            && ($code !== 'sandbox' || $this->sandboxAllowed());
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_values(array_filter(array_keys(config('gateways.adapters', [])), fn ($c) => $this->has($c)));
    }

    /** Allow swapping an adapter (used by tests). */
    public function extend(string $code, GatewayInterface $adapter): void
    {
        $this->resolved[$code] = $adapter;
    }

    private function sandboxAllowed(): bool
    {
        return config('gateways.sandbox_enabled') && ! app()->isProduction();
    }
}
