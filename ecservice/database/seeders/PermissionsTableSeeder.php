<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Upsert the catalog only. Never deletes existing permissions or role assignments.
     */
    public function run()
    {
        PermissionCatalog::sync();
        PermissionCatalog::grantCatalogToAdmin();
    }
}
