<?php

namespace App\Console\Commands;

use App\Models\CenterActivityEntry;
use App\Models\Message;
use Illuminate\Console\Command;

class DiagnoseMediaAttachments extends Command
{
    protected $signature = 'media:diagnose-attachments
                            {--goal= : Limit messages to a goal id}
                            {--limit=100 : Maximum records to scan per model}';

    protected $description = 'Read-only report comparing message/activity attachment DB paths with storage (no deletes)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $goalId = $this->option('goal');

        $this->info('Scanning messages…');
        $messageQuery = Message::query()->orderByDesc('id');
        if ($goalId) {
            $messageQuery->where('goal_id', $goalId);
        }

        $messageIssues = 0;
        foreach ($messageQuery->limit($limit)->get() as $message) {
            if ($message->isSystemMessage()) {
                continue;
            }

            $issue = $this->diagnoseRecord($message, [
                'image' => $message->image_path,
                'file' => $message->file_path,
                'video' => $message->video_path,
            ], fn () => $message->storagePath());

            if ($issue) {
                $messageIssues++;
                $this->line(sprintf('Message #%d (goal %s): %s', $message->id, $message->goal_id, $issue));
            }
        }

        $this->info('Scanning center activity entries…');
        $entryIssues = 0;
        foreach (CenterActivityEntry::query()->orderByDesc('id')->limit($limit)->get() as $entry) {
            $issue = $this->diagnoseRecord($entry, [
                'image' => $entry->image_path,
                'file' => $entry->file_path,
                'video' => $entry->video_path,
            ], fn () => $entry->storagePath());

            if ($issue) {
                $entryIssues++;
                $this->line(sprintf('CenterActivityEntry #%d: %s', $entry->id, $issue));
            }
        }

        $this->info(sprintf('Done. Messages with issues: %d. Center activity entries with issues: %d.', $messageIssues, $entryIssues));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string|null>  $paths
     */
    private function diagnoseRecord($model, array $paths, callable $storagePath): ?string
    {
        $path = $storagePath();
        $hasPath = collect($paths)->contains(fn ($v) => is_string($v) && $v !== '');
        $allEmpty = collect($paths)->every(fn ($v) => $v === '');

        if ($allEmpty) {
            $listing = fetchFiles($path);
            if (is_array($listing) && count($listing) > 0) {
                return 'DB paths empty but storage listing returned '.count($listing).' object(s)';
            }

            return null;
        }

        if (! $hasPath) {
            return null;
        }

        foreach ($paths as $type => $stored) {
            if (! is_string($stored) || $stored === '') {
                continue;
            }

            $absolute = namedFileAbsolutePath($path, $stored);
            $resolved = fileMetaFromStoredName($path, $stored);
            if ($absolute === null && $resolved === null && ! usesBunnyStorage()) {
                return "{$type} path [{$stored}] not readable on disk";
            }
        }

        return null;
    }
}
