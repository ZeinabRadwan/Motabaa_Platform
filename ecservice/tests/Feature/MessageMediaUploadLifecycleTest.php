<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Full HTTP lifecycle tests (requires MySQL with migrations applied).
 *
 * Run only when explicitly enabled:
 *   RUN_MEDIA_INTEGRATION_TESTS=1 php vendor/bin/phpunit tests/Feature/MessageMediaUploadLifecycleTest.php
 */
class MessageMediaUploadLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        if (! filter_var(getenv('RUN_MEDIA_INTEGRATION_TESTS') ?: env('RUN_MEDIA_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_MEDIA_INTEGRATION_TESTS=1 to run media upload lifecycle integration tests.');
        }

        parent::setUp();

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unavailable: '.$e->getMessage());
        }

        foreach (['messages', 'media_attachment_audit_logs', 'users', 'goals'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("Required table [{$table}] is missing. Run migrations on the test database first.");
            }
        }

    }

    public function test_placeholder_integration_suite_requires_manual_seed(): void
    {
        $this->markTestSkipped(
            'Lifecycle HTTP tests need a seeded auth user, goal, and Sanctum token. '
            .'Use staging QA script or extend this suite with project-specific factories.'
        );
    }
}
