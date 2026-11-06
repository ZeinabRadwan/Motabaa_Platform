<?php

namespace App\Console\Commands;

use App\Support\ParentChunkedUpload;
use Illuminate\Console\Command;

class PruneParentUploadChunksCommand extends Command
{
    protected $signature = 'parent-uploads:prune-chunks {--hours=24 : Remove chunked uploads untouched for this many hours}';

    protected $description = 'Delete abandoned temporary chunks from parent goal uploads.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $removed = ParentChunkedUpload::pruneStale($hours * 3600);

        $this->info("Removed {$removed} stale chunked upload(s).");

        return self::SUCCESS;
    }
}
