<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('annual_leave_entitlement')->nullable()->default(21)->after('id_expiry_date');
        });

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        Schema::create('user_file_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_name', 191);
            $table->string('file_extension', 20);
            $table->string('document_type', 30)->default('other');
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'file_name', 'file_extension'], 'user_file_meta_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_file_meta');

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('annual_leave_entitlement');
        });
    }
};
