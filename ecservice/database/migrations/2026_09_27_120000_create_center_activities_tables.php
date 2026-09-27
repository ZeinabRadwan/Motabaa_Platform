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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('center_activity_entries');
        Schema::dropIfExists('center_activity_role');
        Schema::dropIfExists('center_activities');
        Schema::enableForeignKeyConstraints();

        Schema::create('center_activities', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $this->addForeignKeyColumn($table, 'center_id', 'centers', 'id', false);
            $table->string('title');
            $this->addForeignKeyColumn($table, 'created_by', 'users', 'id', false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('center_activity_role', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $this->addForeignKeyColumn($table, 'center_activity_id', 'center_activities', 'id', false);
            $this->addForeignKeyColumn($table, 'role_id', 'roles', 'id', false);
            $table->unique(['center_activity_id', 'role_id']);
        });

        Schema::create('center_activity_entries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $this->addForeignKeyColumn($table, 'center_activity_id', 'center_activities', 'id', false);
            $this->addForeignKeyColumn($table, 'user_id', 'users', 'id', false);
            $table->text('content');
            $table->boolean('parents_can_see')->default(false);
            $table->string('image_path')->nullable();
            $table->string('file_path')->nullable();
            $table->string('video_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('center_activity_entries');
        Schema::dropIfExists('center_activity_role');
        Schema::dropIfExists('center_activities');
        Schema::enableForeignKeyConstraints();
    }
};
