<?php

namespace Tests\Unit;

use App\Models\MediaAttachmentAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaAttachmentAuditLogTest extends TestCase
{
    public function test_correlation_id_uses_request_header_when_uuid(): void
    {
        $uuid = (string) Str::uuid();
        $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => $uuid,
        ]));

        $this->assertSame($uuid, MediaAttachmentAuditLog::correlationIdFromRequest());
    }

    public function test_correlation_id_generates_uuid_when_header_missing(): void
    {
        $this->app->instance('request', Request::create('/', 'GET'));

        $id = MediaAttachmentAuditLog::correlationIdFromRequest();

        $this->assertTrue(Str::isUuid($id));
    }
}
