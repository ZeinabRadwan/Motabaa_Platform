<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;

class BackfillMessageMediaPaths extends Command
{
    protected $signature = 'messages:backfill-media-paths
                            {--dry-run : Report changes without writing to the database}
                            {--chunk=200 : Number of messages processed per batch}';

    protected $description = 'Populate message image_path, file_path, and video_path from Bunny/local storage for legacy records';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $updated = 0;
        $skipped = 0;

        Message::query()
            ->where(function ($query) {
                $query->whereNull('image_path')
                    ->orWhereNull('file_path')
                    ->orWhereNull('video_path');
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($messages) use ($dryRun, &$updated, &$skipped) {
                foreach ($messages as $message) {
                    if ($message->isSystemMessage()) {
                        $skipped++;

                        continue;
                    }

                    $changes = $this->resolvePathChanges($message);
                    if ($changes === []) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line(sprintf(
                            'Message #%d: %s',
                            $message->id,
                            json_encode($changes, JSON_UNESCAPED_SLASHES),
                        ));
                    } else {
                        $message->forceFill($changes)->save();
                    }

                    $updated++;
                }
            });

        $this->info(sprintf(
            '%s %d message(s); skipped %d.',
            $dryRun ? 'Would update' : 'Updated',
            $updated,
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function resolvePathChanges(Message $message): array
    {
        $changes = [];
        $map = [
            'image_path' => Message::IMAGE_NAME,
            'file_path' => Message::FILE_NAME,
            'video_path' => Message::VIDEO_NAME,
        ];

        foreach ($map as $column => $baseName) {
            if ($message->getAttribute($column) !== null) {
                continue;
            }

            $found = fetchFiles($message->storagePath(), $baseName);
            $changes[$column] = ($found && !empty($found['file_extension']))
                ? $baseName.'.'.$found['file_extension']
                : '';
        }

        return $changes;
    }
}
