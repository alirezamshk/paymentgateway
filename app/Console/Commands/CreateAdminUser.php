<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email} {--name=Admin}';

    protected $description = 'Create (or promote) an admin panel user';

    public function handle(): int
    {
        $password = $this->secret('Password (min 12 characters)');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->option('name'), 'password' => $password, 'is_admin' => true],
        );

        $this->info('Admin user saved.');

        return self::SUCCESS;
    }
}
