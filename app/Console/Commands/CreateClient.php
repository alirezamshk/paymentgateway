<?php

namespace App\Console\Commands;

use App\Services\ClientService;
use Illuminate\Console\Command;

class CreateClient extends Command
{
    protected $signature = 'client:create {name} {slug} {--webhook-url=} {--return-url=}';

    protected $description = 'Create a client site and print its API credentials once';

    public function handle(ClientService $clients): int
    {
        $result = $clients->create([
            'name' => $this->argument('name'),
            'slug' => $this->argument('slug'),
            'webhook_url' => $this->option('webhook-url'),
            'return_url' => $this->option('return-url'),
        ]);

        $this->info("Client {$result['client']->public_id} created. Store these now - they are not shown again:");
        $this->line("X-Client-Id:     {$result['key_id']}");
        $this->line("Client secret:   {$result['secret']}");
        $this->line("Webhook secret:  {$result['webhook_secret']}");

        return self::SUCCESS;
    }
}
