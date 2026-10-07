<?php

namespace Database\Seeders;

use App\Models\GatewayProvider;
use Illuminate\Database\Seeder;

class GatewayProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['code' => 'zarinpal', 'name' => 'ZarinPal', 'config' => ['sandbox' => true]],
            ['code' => 'sepehr', 'name' => 'Sepehr (Saderat)', 'config' => []],
            ['code' => 'asanpardakht', 'name' => 'Asan Pardakht', 'config' => []],
            ['code' => 'sepordeh', 'name' => 'Sepordeh', 'config' => ['amount_currency' => 'IRT']],
            ['code' => 'sandbox', 'name' => 'Sandbox (test only)', 'config' => []],
        ];

        foreach ($providers as $provider) {
            // Providers start disabled except the sandbox: enable each one after it has been
            // verified against the PSP's test environment.
            GatewayProvider::firstOrCreate(['code' => $provider['code']], $provider + [
                'status' => $provider['code'] === 'sandbox' ? 'active' : 'disabled',
            ]);
        }
    }
}
