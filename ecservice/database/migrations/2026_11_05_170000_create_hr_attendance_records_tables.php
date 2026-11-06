<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Electronic (website GPS) attendance:
 *  - hr_attendance_records: one row per employee and attendance date (the scheduled day the check-in belongs to),
 *    with the check-in and check-out location evidence and the rules (shift times, policy version, GPS requirement)
 *    it was evaluated with;
 *  - hr_attendance_punches: every check-in / check-out attempt, accepted or rejected, as an audit trail.
 * Manual records entered by HR share hr_attendance_records (attendance_method = manual, see 2026_11_05_190000),
 * which is why the check-in columns are nullable: a manual record may lack its check-in until it is corrected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_attendance_records')) {
            Schema::create('hr_attendance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->date('attendance_date');
                $table->string('attendance_method', 20)->default('electronic');
                // checked_in | checked_out | missing_check_out
                $table->string('status', 20)->default('checked_in');
                // Equals employee_id while the session is open, null once closed: the unique index allows one open
                // session per employee whatever its date.
                $table->unsignedBigInteger('open_session_key')->nullable()->unique();

                $table->foreignId('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
                $table->dateTime('scheduled_start_at')->nullable();
                $table->dateTime('scheduled_end_at')->nullable();
                $table->foreignId('attendance_config_id')->nullable()->constrained('hr_employee_attendance_configs')->nullOnDelete();
                $table->foreignId('attendance_policy_id')->nullable()->constrained('hr_attendance_policies')->nullOnDelete();
                $table->foreignId('attendance_policy_version_id')->nullable()->constrained('hr_attendance_policy_versions')->nullOnDelete();
                $table->string('gps_requirement', 20);
                $table->string('missing_checkout_action', 30)->nullable();
                // Check-out window end: an open session past it is closed with the missing check-out action.
                $table->dateTime('check_out_deadline_at')->nullable();

                $table->foreignId('work_location_id')->nullable()->constrained('hr_work_locations')->nullOnDelete();
                $table->unsignedInteger('location_radius_meters')->nullable();

                $table->dateTime('check_in_at')->nullable();
                $table->decimal('check_in_latitude', 10, 7)->nullable();
                $table->decimal('check_in_longitude', 10, 7)->nullable();
                $table->decimal('check_in_accuracy_meters', 10, 2)->nullable();
                $table->decimal('check_in_distance_meters', 12, 2)->nullable();
                $table->string('check_in_verification', 30)->nullable();
                $table->string('check_in_ip', 45)->nullable();
                $table->string('check_in_user_agent', 255)->nullable();

                $table->dateTime('check_out_at')->nullable();
                $table->decimal('check_out_latitude', 10, 7)->nullable();
                $table->decimal('check_out_longitude', 10, 7)->nullable();
                $table->decimal('check_out_accuracy_meters', 10, 2)->nullable();
                $table->decimal('check_out_distance_meters', 12, 2)->nullable();
                $table->string('check_out_verification', 30)->nullable();
                $table->string('check_out_ip', 45)->nullable();
                $table->string('check_out_user_agent', 255)->nullable();

                $table->unsignedSmallInteger('late_minutes')->default(0);
                $table->unsignedSmallInteger('early_departure_minutes')->default(0);
                $table->unsignedSmallInteger('worked_minutes')->nullable();
                $table->boolean('needs_review')->default(false);
                $table->json('review_reasons')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'attendance_date'], 'hr_att_records_employee_date_unique');
                $table->index(['center_id', 'attendance_date']);
                $table->index(['status', 'check_out_deadline_at']);
            });
        }

        if (! Schema::hasTable('hr_attendance_punches')) {
            Schema::create('hr_attendance_punches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('attendance_record_id')->nullable()->constrained('hr_attendance_records')->nullOnDelete();
                // check_in | check_out
                $table->string('type', 20);
                // accepted | rejected | duplicate
                $table->string('result', 20);
                // Rejection reason (validation.hr key) or null.
                $table->string('reason', 60)->nullable();
                $table->dateTime('attempted_at');
                $table->date('attendance_date')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('accuracy_meters', 10, 2)->nullable();
                $table->decimal('distance_meters', 12, 2)->nullable();
                $table->foreignId('work_location_id')->nullable()->constrained('hr_work_locations')->nullOnDelete();
                $table->string('verification', 30)->nullable();
                // Browser geolocation failure reported by the client (permission_denied, timeout, ...).
                $table->string('location_error', 30)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['employee_id', 'attempted_at']);
                $table->index(['center_id', 'attempted_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_punches');
        Schema::dropIfExists('hr_attendance_records');
    }
};
