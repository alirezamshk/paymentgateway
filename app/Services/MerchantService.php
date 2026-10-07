<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Exceptions\ApiException;
use App\Gateways\Contracts\SupportsCredentialCheck;
use App\Gateways\Data\GatewayCheckResult;
use App\Gateways\GatewayManager;
use App\Models\Client;
use App\Models\GatewayProvider;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;

/**
 * Merchant management. Always scoped to a Client; credentials are validated against
 * what the provider's adapter requires and stored encrypted.
 */
class MerchantService
{
    private const STANDARD_KEYS = ['merchant_identifier', 'terminal_identifier', 'username', 'password', 'api_key'];

    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, provider: string, credentials?: array<string, mixed>, is_default?: bool, status?: string}  $data
     */
    public function create(Client $client, array $data, string $actorType, ?int $actorId): Merchant
    {
        $provider = $this->provider($data['provider']);
        $credentials = $data['credentials'] ?? [];
        $this->assertCredentials($provider, $credentials);

        return DB::transaction(function () use ($client, $provider, $data, $credentials, $actorType, $actorId) {
            $merchant = new Merchant([
                'client_id' => $client->id,
                'provider_id' => $provider->id,
                'name' => $data['name'],
                'status' => $data['status'] ?? RecordStatus::Active->value,
                'is_default' => false,
            ]);
            $this->fillCredentials($merchant, $credentials, replace: true);
            $merchant->save();

            // The first merchant of a client becomes its default automatically.
            if (! empty($data['is_default']) || ! $client->merchants()->where('is_default', true)->exists()) {
                $this->setDefault($merchant, $actorType, $actorId, audit: false);
            }

            $this->audit->log($actorType, $actorId, 'merchant.created', $client->id, 'merchant', $merchant->public_id, ['provider' => $provider->code]);

            return $merchant->refresh();
        });
    }

    /**
     * Credentials are merged: keys that are omitted or empty keep their current value,
     * so secrets never need to be re-sent (or displayed) to change something else.
     *
     * @param  array{name?: string, provider?: string, credentials?: array<string, mixed>, status?: string, is_default?: bool}  $data
     */
    public function update(Merchant $merchant, array $data, string $actorType, ?int $actorId): Merchant
    {
        return DB::transaction(function () use ($merchant, $data, $actorType, $actorId) {
            $replace = false;

            if (isset($data['provider']) && $data['provider'] !== $merchant->provider->code) {
                $merchant->provider()->associate($this->provider($data['provider']));
                $replace = true;
            }

            if (isset($data['name'])) {
                $merchant->name = $data['name'];
            }

            if (isset($data['status'])) {
                $merchant->status = RecordStatus::from($data['status']);
            }

            $this->fillCredentials($merchant, $data['credentials'] ?? [], $replace);
            $this->assertCredentials($merchant->provider, $merchant->credentials());
            $merchant->save();

            if (! empty($data['is_default'])) {
                $this->setDefault($merchant, $actorType, $actorId, audit: false);
            }

            $this->audit->log($actorType, $actorId, 'merchant.updated', $merchant->client_id, 'merchant', $merchant->public_id, [
                'fields' => array_keys(array_diff_key($data, ['credentials' => 1])),
                'credential_keys_changed' => array_keys(array_filter($data['credentials'] ?? [], fn ($v) => $v !== null && $v !== '')),
            ]);

            return $merchant->refresh();
        });
    }

    public function setDefault(Merchant $merchant, string $actorType, ?int $actorId, bool $audit = true): void
    {
        DB::transaction(function () use ($merchant) {
            // Lock the client's merchants so two concurrent "set default" calls serialize.
            Merchant::where('client_id', $merchant->client_id)->lockForUpdate()->get(['id']);
            Merchant::where('client_id', $merchant->client_id)->where('id', '!=', $merchant->id)->update(['is_default' => false]);
            $merchant->forceFill(['is_default' => true])->save();
        });

        if ($audit) {
            $this->audit->log($actorType, $actorId, 'merchant.set_default', $merchant->client_id, 'merchant', $merchant->public_id);
        }
    }

    public function setStatus(Merchant $merchant, RecordStatus $status, string $actorType, ?int $actorId): void
    {
        $merchant->update(['status' => $status]);

        $this->audit->log($actorType, $actorId, 'merchant.'.($status === RecordStatus::Active ? 'enabled' : 'disabled'), $merchant->client_id, 'merchant', $merchant->public_id);
    }

    public function testCredentials(Merchant $merchant, string $actorType, ?int $actorId): GatewayCheckResult
    {
        $adapter = $this->gateways->for($merchant->provider);

        $missing = array_diff(array_keys($adapter->requiredCredentials()), array_keys($merchant->credentials()));

        $result = match (true) {
            $missing !== [] => new GatewayCheckResult(false, 'Missing credentials: '.implode(', ', $missing)),
            $adapter instanceof SupportsCredentialCheck => $adapter->checkCredentials($merchant),
            default => new GatewayCheckResult(true, 'All required credentials are present. This provider has no side-effect-free test call; confirm with a sandbox payment.'),
        };

        $this->audit->log($actorType, $actorId, 'merchant.credentials_tested', $merchant->client_id, 'merchant', $merchant->public_id, ['successful' => $result->successful]);

        return $result;
    }

    private function provider(string $code): GatewayProvider
    {
        $provider = GatewayProvider::where('code', $code)->first();

        if ($provider === null || ! $this->gateways->has($code)) {
            throw new ApiException('PROVIDER_NOT_SUPPORTED', "Unknown payment provider [{$code}].", 422);
        }

        return $provider;
    }

    /** @param array<string, mixed> $credentials */
    private function assertCredentials(GatewayProvider $provider, array $credentials): void
    {
        $required = array_keys($this->gateways->for($provider)->requiredCredentials());
        $present = array_keys(array_filter($credentials, fn ($v) => $v !== null && $v !== ''));
        $missing = array_values(array_diff($required, $present));

        if ($missing !== []) {
            throw new ApiException('INVALID_MERCHANT_CREDENTIALS', 'Missing credentials for provider '.$provider->code.'.', 422, ['missing' => $missing]);
        }
    }

    /** @param array<string, mixed> $credentials */
    private function fillCredentials(Merchant $merchant, array $credentials, bool $replace): void
    {
        $credentials = array_filter($credentials, fn ($v) => $v !== null && $v !== '');

        if ($replace) {
            $merchant->merchant_identifier = $merchant->terminal_identifier = $merchant->username = null;
            $merchant->encrypted_password = $merchant->encrypted_api_key = null;
            $merchant->encrypted_config = null;
        }

        $columns = [
            'merchant_identifier' => 'merchant_identifier',
            'terminal_identifier' => 'terminal_identifier',
            'username' => 'username',
            'password' => 'encrypted_password',
            'api_key' => 'encrypted_api_key',
        ];

        foreach ($columns as $key => $column) {
            if (array_key_exists($key, $credentials)) {
                $merchant->{$column} = (string) $credentials[$key];
            }
        }

        $extra = array_diff_key($credentials, array_flip(self::STANDARD_KEYS));

        if ($extra !== []) {
            $merchant->encrypted_config = array_merge($merchant->encrypted_config ?? [], array_map('strval', $extra));
        }
    }
}
