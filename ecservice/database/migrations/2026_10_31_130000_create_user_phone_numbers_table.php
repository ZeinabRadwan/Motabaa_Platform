<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional phone numbers per account (used for parent-only accounts). Schema only: no data is copied
 * here; run `php artisan parent:backfill-phone-numbers` (dry-run by default) to materialize primaries.
 *
 * "One primary per user" is enforced by ParentPhoneNumberService (transaction + lockForUpdate) because
 * MySQL has no portable partial unique index (unique where is_primary = 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_phone_numbers')) {
            return;
        }

        Schema::create('user_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone', 20);
            $table->string('raw_phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method', 40)->nullable();
            $table->string('label', 50)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'phone']);
            $table->index('phone');
            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_phone_numbers');
    }
};
