<?php

namespace App\Services;

use App\Enums\CredentialStatus;
use App\Enums\RecordStatus;
use App\Models\Client;
use App\Models\ClientCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Client lifecycle and credential management. Plaintext secrets are only ever returned
 * from these methods (to be shown once) and are stored encrypted.
 */
class ClientService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, slug: string, webhook_url?: ?string, return_url?: ?string}  $data
     * @return array{client: Client, key_id: string, secret: string, webhook_secret: string}
     */
    public function create(array $data, ?int $adminId = null): array
    {
        return DB::transaction(function () use ($data, $adminId) {
            $webhookSecret = $this->generateSecret('whsec');

            $client = Client::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => RecordStatus::Active,
                'webhook_url' => $data['webhook_url'] ?? null,
                'return_url' => $data['return_url'] ?? null,
                'webhook_secret' => $webhookSecret,
            ] + array_intersect_key($data, array_flip([
                'commission_bps', 'commission_fixed_irr', 'settlement_delay_hours', 'iban', 'account_holder',
            ])));

            [$credential, $secret] = $this->issueCredential($client);

            $this->audit->log('admin', $adminId, 'client.created', $client->id, 'client', $client->public_id, ['name' => $client->name]);

            return ['client' => $client, 'key_id' => $credential->key_id, 'secret' => $secret, 'webhook_secret' => $webhookSecret];
        });
    }

    /**
     * Issue a new API credential. Old credentials stay active until revoked, which allows
     * zero-downtime rotation (deploy the new key on the client site, then revoke the old one).
     *
     * @return array{0: ClientCredential, 1: string}
     */
    public function issueCredential(Client $client, ?int $adminId = null): array
    {
        $secret = $this->generateSecret('tksk');

        $credential = $client->credentials()->create([
            'key_id' => 'tkc_'.strtolower((string) Str::ulid()),
            'encrypted_secret' => $secret,
            'status' => CredentialStatus::Active,
        ]);

        $this->audit->log('admin', $adminId, 'client.credential_issued', $client->id, 'client_credential', $credential->key_id);

        return [$credential, $secret];
    }

    public function revokeCredential(ClientCredential $credential, ?int $adminId = null): void
    {
        $credential->update(['status' => CredentialStatus::Revoked, 'revoked_at' => now()]);

        $this->audit->log('admin', $adminId, 'client.credential_revoked', $credential->client_id, 'client_credential', $credential->key_id);
    }

    public function rotateWebhookSecret(Client $client, ?int $adminId = null): string
    {
        $secret = $this->generateSecret('whsec');
        $client->update(['webhook_secret' => $secret]);

        $this->audit->log('admin', $adminId, 'client.webhook_secret_rotated', $client->id, 'client', $client->public_id);

        return $secret;
    }

    private function generateSecret(string $prefix): string
    {
        return $prefix.'_'.bin2hex(random_bytes(32));
    }
}
