<?php

namespace Tests\Unit;

use App\Models\System\ExportExcel;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ExportDownloadUrlTest extends TestCase
{
    public function test_download_url_uses_app_url_and_api_prefix(): void
    {
        Config::set('app.url', 'https://api.athrplatform.com');

        $url = ExportExcel::downloadUrl('test-token');

        $this->assertSame(
            'https://api.athrplatform.com/api/file/download?token=test-token',
            $url
        );
    }
}
