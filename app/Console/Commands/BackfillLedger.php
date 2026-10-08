<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Settlement\LedgerService;
use Illuminate\Console\Command;

class BackfillLedger extends Command
{
    protected $signature = 'settlement:backfill {--client= : client slug (default: all clients)}';

    protected $description = 'Credit paid payments that have no settlement ledger entry yet (uses current commission settings)';

    public function handle(LedgerService $ledger): int
    {
        $client = $this->option('client') ? Client::where('slug', $this->option('client'))->firstOrFail() : null;
        $count = $ledger->backfill($client);

        $this->info("Credited {$count} payment(s).");

        return self::SUCCESS;
    }
}
