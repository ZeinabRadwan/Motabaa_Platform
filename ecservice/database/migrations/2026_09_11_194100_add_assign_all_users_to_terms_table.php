<?php

use App\Models\Term;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('terms', 'assign_all_users')) {
            Schema::table('terms', function (Blueprint $table) {
                if (Schema::hasColumn('terms', 'periods_count')) {
                    $table->boolean('assign_all_users')->default(false)->after('periods_count');
                } else {
                    $table->boolean('assign_all_users')->default(false);
                }
            });
        }

        if (!Schema::hasTable('term_user')) {
            return;
        }

        Term::query()->select('id', 'center_id')->orderBy('id')->chunkById(100, function ($terms) {
            foreach ($terms as $term) {
                $staffIds = Term::assignableStaffQuery($term->center_id)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                if (!count($staffIds)) {
                    continue;
                }

                $assignedIds = DB::table('term_user')
                    ->where('term_id', $term->id)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if (!count(array_diff($staffIds, $assignedIds))) {
                    DB::table('terms')->where('id', $term->id)->update(['assign_all_users' => 1]);
                }
            }
        });

        if (class_exists(\App\Support\ReferenceCache::class)) {
            \App\Support\ReferenceCache::bump('terms');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('terms', 'assign_all_users')) {
            Schema::table('terms', function (Blueprint $table) {
                $table->dropColumn('assign_all_users');
            });
        }
    }
};
