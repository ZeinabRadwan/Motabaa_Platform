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

        if (! empty($result['cases_view_migration'])) {
            $m = $result['cases_view_migration'];
            $this->info('Cases view migration: roles with mine/all='.$m['roles_migrated'].', direct users='.$m['users_migrated'].', legacy revoked='.$m['legacy_revoked']);
        }

        if (! empty($result['center_activities_migration'])) {
            $m = $result['center_activities_migration'];
            $this->info('Center activities migration: roles updated='.$m['roles_updated'].', legacy revoked='.$m['legacy_revoked']);
        }

        if (! empty($result['hr_branch_scope_migration'])) {
            $this->info('HR all-branches access granted to roles: '.$result['hr_branch_scope_migration']['roles_granted']);
        }

        if (! empty($result['hr_attendance_config_migration'])) {
            $this->info('HR attendance configuration permission granted to roles: '.$result['hr_attendance_config_migration']['roles_granted']);
        }

        if (! empty($result['parent_assessment_migration'])) {
            $m = $result['parent_assessment_migration'];
            $this->info('Parent assessment migration: roles granted='.$m['roles_granted'].', users granted='.$m['users_granted']);
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
