<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_attachment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('operation', 32);
            $table->string('outcome', 16);
            $table->string('stage', 64)->nullable();
            $table->string('attachable_type', 191)->nullable()->index();
            $table->unsignedBigInteger('attachable_id')->nullable()->index();
            $table->string('context_type', 64)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('center_id')->nullable()->index();
            $table->string('original_filename')->nullable();
            $table->string('stored_filename')->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['operation', 'outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_attachment_audit_logs');
    }
};
