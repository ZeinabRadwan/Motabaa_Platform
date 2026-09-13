<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('terms', 'periods_count')) {
            Schema::table('terms', function (Blueprint $table) {
                $table->unsignedTinyInteger('periods_count')->default(4)->after('ends_at');
            });
        }

        if (!Schema::hasTable('term_periods')) {
            Schema::create('term_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('term_id');
                $table->unsignedTinyInteger('period_index');
                $table->date('evaluation_at');
                $table->timestamps();
                $table->unique(['term_id', 'period_index']);
            });

            try {
                Schema::table('term_periods', function (Blueprint $table) {
                    $table->foreign('term_id')->references('id')->on('terms')->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // Some production databases cannot add this FK; unique(term_id, period_index) is enough.
            }
        }

        if (!Schema::hasTable('term_periods')) {
            return;
        }

        $terms = DB::table('terms')->get();
        foreach ($terms as $term) {
            if (DB::table('term_periods')->where('term_id', $term->id)->exists()) {
                continue;
            }

            $dates = array_values(array_filter([
                $term->first_evaluation_at,
                $term->second_evaluation_at,
                $term->third_evaluation_at,
                $term->final_evaluation_at ?: $term->ends_at,
            ]));

            if (!count($dates) && $term->ends_at) {
                $dates = [$term->ends_at];
            }

            if (Schema::hasColumn('terms', 'periods_count')) {
                DB::table('terms')->where('id', $term->id)->update([
                    'periods_count' => max(count($dates), 1),
                ]);
            }

            foreach ($dates as $index => $date) {
                DB::table('term_periods')->insert([
                    'term_id' => $term->id,
                    'period_index' => $index,
                    'evaluation_at' => $date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep schema; the original term-periods migration owns rollback.
    }
};
