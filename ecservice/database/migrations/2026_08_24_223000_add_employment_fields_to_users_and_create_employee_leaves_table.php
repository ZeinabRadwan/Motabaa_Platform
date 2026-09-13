<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title', 150)->nullable()->after('current_work');
            $table->string('department', 50)->nullable()->after('job_title');
            $table->string('contract_type', 30)->nullable()->after('department');
            $table->date('hire_date')->nullable()->after('contract_type');
            $table->date('contract_end_date')->nullable()->after('hire_date');
            $table->date('id_expiry_date')->nullable()->after('contract_end_date');
        });

        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedSmallInteger('days')->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'job_title',
                'department',
                'contract_type',
                'hire_date',
                'contract_end_date',
                'id_expiry_date',
            ]);
        });
    }
};
