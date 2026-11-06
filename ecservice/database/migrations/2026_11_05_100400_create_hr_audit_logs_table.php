<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only field-level history for HR records (old/new values). The existing `logs` table remains the
 * human-readable activity feed; it has no before/after values, which HR and payroll changes require.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_audit_logs')) {
            return;
        }

        Schema::create('hr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('via_impersonation')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('url', 255)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['center_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_audit_logs');
    }
};
