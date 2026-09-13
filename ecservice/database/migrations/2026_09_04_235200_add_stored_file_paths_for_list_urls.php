<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addNullableString('users', 'image_path', 'id');
        $this->addNullableString('scases', 'image_path', 'id');
        $this->addNullableString('centers', 'logo_path', 'id');
        $this->addNullableString('messages', 'image_path', 'id');
        $this->addNullableString('messages', 'file_path', 'image_path');
        $this->addNullableString('messages', 'video_path', 'file_path');
        $this->addNullableString('scases_payments', 'file_path', 'id');
        $this->addNullableString('centers_payments', 'file_path', 'id');
        $this->addNullableString('attendances', 'file_path', 'id');
    }

    public function down(): void
    {
        $this->dropIfExists('users', 'image_path');
        $this->dropIfExists('scases', 'image_path');
        $this->dropIfExists('centers', 'logo_path');
        $this->dropIfExists('messages', ['image_path', 'file_path', 'video_path']);
        $this->dropIfExists('scases_payments', 'file_path');
        $this->dropIfExists('centers_payments', 'file_path');
        $this->dropIfExists('attendances', 'file_path');
    }

    private function addNullableString(string $table, string $column, string $after): void
    {
        if (!Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $after) {
            $blueprint->string($column)->nullable()->after($after);
        });
    }

    private function dropIfExists(string $table, string|array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $columns = (array) $columns;
        $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
        if ($existing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($existing) {
            $blueprint->dropColumn($existing);
        });
    }
};
