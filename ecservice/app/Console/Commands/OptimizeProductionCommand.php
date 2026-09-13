<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class OptimizeProductionCommand extends Command
{
    protected $signature = 'motabaa:optimize {--force : Run even when APP_ENV is local}';

    protected $description = 'Cache config and views for production. Skips unsupported Redis, queue workers, and route cache.';

    public function handle(): int
    {
        if (!app()->environment('production') && !$this->option('force')) {
            $this->error('Refusing to optimize a non-production environment. Re-run with --force if you mean it.');

            return self::FAILURE;
        }

        $this->info('Caching configuration...');
        Artisan::call('config:cache');
        $this->line(trim(Artisan::output()));

        $this->info('Caching views...');
        Artisan::call('view:cache');
        $this->line(trim(Artisan::output()));

        $this->info('Attempting route cache...');
        try {
            Artisan::call('route:cache');
            $this->line(trim(Artisan::output()));
        } catch (Throwable $e) {
            $this->warn('Route cache skipped: '.$e->getMessage());
            $this->warn('LIVE and this repo still register closure routes in routes/web.php and routes/api.php.');
        }

        $this->newLine();
        $this->table(['Check', 'Status', 'Action'], $this->environmentReport());

        return self::SUCCESS;
    }

    private function environmentReport(): array
    {
        $redisExt = extension_loaded('redis');
        $predis = class_exists(\Predis\Client::class);
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $opcacheOn = is_array($opcache) && !empty($opcache['opcache_enabled']);

        return [
            [
                'APP_DEBUG',
                config('app.debug') ? 'true (unsafe for LIVE)' : 'false',
                'Set APP_DEBUG=false on api.motabaah.com only',
            ],
            [
                'Cache driver',
                config('cache.default'),
                $redisExt || $predis
                    ? 'Redis client exists here; confirm redis-cli ping on LIVE before CACHE_DRIVER=redis'
                    : 'Keep file. LIVE is LiteSpeed/WHM; no Redis package is installed',
            ],
            [
                'Queue',
                config('queue.default'),
                'Keep sync. No jobs table or worker was verified on LIVE. WhatsApp already runs after the HTTP response',
            ],
            [
                'OPcache',
                $opcacheOn ? 'enabled' : 'not enabled in this process',
                'On LiteSpeed/WHM: MultiPHP INI Editor → enable opcache.enable=1',
            ],
            [
                'PDF/Excel',
                'sync',
                'Left in-request: the download token is returned in the same response and files live in sys_get_temp_dir()',
            ],
        ];
    }
}
