<?php

use App\Support\MigrationForeignKeyHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use MigrationForeignKeyHelpers;

    public function up(): void
    {
        if (Schema::hasColumn('center_activities', 'term_id')) {
            $this->dropForeignKeyIfExists('center_activities', 'term_id');
            Schema::table('center_activities', function (Blueprint $table) {
                $table->dropColumn('term_id');
            });
        }

        Schema::table('center_activities', function (Blueprint $table) {
            $this->addForeignKeyColumn($table, 'term_id', 'terms', 'id', true, 'set null', 'center_id');
        });

        if (! Schema::hasColumn('center_activities', 'parents_can_see')) {
            Schema::table('center_activities', function (Blueprint $table) {
                $table->boolean('parents_can_see')->default(false)->after('title');
            });
        }

        if (Schema::hasColumn('center_activity_entries', 'parents_can_see')) {
            Schema::table('center_activity_entries', function (Blueprint $table) {
                $table->dropColumn('parents_can_see');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('center_activity_entries', 'parents_can_see')) {
            Schema::table('center_activity_entries', function (Blueprint $table) {
                $table->boolean('parents_can_see')->default(false)->after('content');
            });
        }

        if (Schema::hasColumn('center_activities', 'term_id')) {
            $this->dropForeignKeyIfExists('center_activities', 'term_id');
        }

        Schema::table('center_activities', function (Blueprint $table) {
            if (Schema::hasColumn('center_activities', 'term_id')) {
                $table->dropColumn('term_id');
            }
            if (Schema::hasColumn('center_activities', 'parents_can_see')) {
                $table->dropColumn('parents_can_see');
            }
        });
    }
};
