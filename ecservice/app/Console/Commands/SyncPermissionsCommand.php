<?php

namespace App\Console\Commands;

use App\Support\PermissionCatalog;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync {--no-admin : Do not grant catalog permissions to the admin role}';

    protected $description = 'Create missing Motabaah permissions without deleting existing ones';

    public function handle(): int
    {
        $result = PermissionCatalog::sync();

        if (! $this->option('no-admin')) {
            PermissionCatalog::grantCatalogToAdmin();
        }

        $this->info('Created: '.count($result['created']));
        foreach ($result['created'] as $name) {
            $this->line('  + '.$name);
        }

        $this->info('Updated labels: '.count($result['updated']));
        foreach ($result['updated'] as $name) {
            $this->line('  ~ '.$name);
        }

        return self::SUCCESS;
    }
}
