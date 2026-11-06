<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual attendance in the central attendance records:
 *  - hr_attendance_records: check-in becomes optional (a manual record may only know the check-out until it is
 *    corrected), the computed attendance status, who entered / last changed the record and why, and notes;
 *  - hr_attendance_record_changes: every manual creation and change with the previous and new values, the reason,
 *    who made it and when.
 * Electronic records keep attendance_method = electronic; HR corrections never change the method.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->makeCheckInNullable();

        Schema::table('hr_attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_attendance_records', 'attendance_status')) {
                // present | late | early_departure | late_early_departure | missing_check_in | missing_check_out |
                // off_schedule | in_progress (AttendanceRecord::ATTENDANCE_STATUSES), recomputed on every save.
                $table->string('attendance_status', 30)->nullable();
                // Manual records: form | bulk | import. Null for electronic records.
                $table->string('entry_source', 20)->nullable();
                // AttendanceRecord::ENTRY_REASONS key, with free text in entry_reason_notes.
                $table->string('entry_reason', 40)->nullable();
                $table->string('entry_reason_notes', 500)->nullable();
                $table->text('notes')->nullable();
                // The shift used for a manual record differs from the one scheduled that day.
                $table->boolean('shift_overridden')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                // Last manual change (updated_at also moves with automatic updates such as location checks).
                $table->dateTime('modified_at')->nullable();

                $table->index(['center_id', 'attendance_status']);
                $table->index(['center_id', 'attendance_method']);
            }
        });

        DB::table('hr_attendance_records')->whereNull('attendance_status')->update([
            'attendance_status' => DB::raw("CASE
                WHEN check_in_at IS NULL THEN 'missing_check_in'
                WHEN status = 'checked_in' THEN 'in_progress'
                WHEN check_out_at IS NULL THEN 'missing_check_out'
                WHEN scheduled_start_at IS NULL THEN 'off_schedule'
                WHEN late_minutes > 0 AND early_departure_minutes > 0 THEN 'late_early_departure'
                WHEN late_minutes > 0 THEN 'late'
                WHEN early_departure_minutes > 0 THEN 'early_departure'
                ELSE 'present' END"),
        ]);

        if (! Schema::hasTable('hr_attendance_record_changes')) {
            Schema::create('hr_attendance_record_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('attendance_record_id')->constrained('hr_attendance_records')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                // created | updated | check_in_corrected | check_out_corrected
                $table->string('action', 30);
                // form | bulk | import
                $table->string('source', 20);
                $table->string('entry_reason', 40)->nullable();
                $table->text('reason');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('via_impersonation')->default(false);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                // Explicit names: the generated ones exceed MySQL's 64-character limit.
                $table->index(['attendance_record_id', 'created_at'], 'hr_att_record_changes_record_created_idx');
                $table->index(['center_id', 'created_at'], 'hr_att_record_changes_center_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_record_changes');

        Schema::table('hr_attendance_records', function (Blueprint $table) {
            if (Schema::hasColumn('hr_attendance_records', 'attendance_status')) {
                $table->dropIndex(['center_id', 'attendance_status']);
                $table->dropIndex(['center_id', 'attendance_method']);
                $table->dropConstrainedForeignId('created_by');
                $table->dropConstrainedForeignId('updated_by');
                $table->dropColumn(['attendance_status', 'entry_source', 'entry_reason', 'entry_reason_notes', 'notes', 'shift_overridden', 'modified_at']);
            }
        });
    }

    /**
     * Databases created before manual attendance have NOT NULL check-in columns. Without doctrine/dbal the
     * change is written per driver; SQLite databases are always created by the updated create migration.
     */
    private function makeCheckInNullable(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE hr_attendance_records MODIFY check_in_at DATETIME NULL, MODIFY check_in_verification VARCHAR(30) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE hr_attendance_records ALTER COLUMN check_in_at DROP NOT NULL, ALTER COLUMN check_in_verification DROP NOT NULL');
        }
    }
};
