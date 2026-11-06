<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Temporary, private storage for one parent upload sent in fixed-size chunks.
 *
 * LIVE is proxied by Cloudflare, which aborts any request whose origin response takes
 * longer than ~100 s (HTTP 524). Sending large videos as several small requests keeps
 * every request well inside that window on slow mobile uplinks.
 */
class ParentChunkedUpload
{
    public const CHUNK_BYTES = 4 * 1024 * 1024;

    public const STALE_SECONDS = 86400;

    public const MAX_ACTIVE_PER_USER = 3;

    /**
     * An unfinished upload no chunk has reached for this long is treated as abandoned when
     * the user needs its slot. It must exceed the client's per-chunk timeout plus retries.
     */
    public const IDLE_SECONDS = 600;

    public const UPLOAD_ID_PATTERN = '/^[A-Za-z0-9_-]{16,64}$/';

    private const ROOT = 'app/parent-upload-chunks';

    private const META = 'meta.json';

    private const COMPLETED = 'completed.json';

    private const ASSEMBLED = 'assembled.bin';

    public function __construct(private int $userId, private string $uploadId)
    {
        if (! preg_match(self::UPLOAD_ID_PATTERN, $uploadId)) {
            throw new \InvalidArgumentException('Invalid upload id.');
        }
    }

    public static function root(): string
    {
        return storage_path(self::ROOT);
    }

    public static function expectedChunks(int $totalSize): int
    {
        return max(1, (int) ceil($totalSize / self::CHUNK_BYTES));
    }

    public static function expectedChunkSize(int $index, int $totalSize): int
    {
        $chunks = self::expectedChunks($totalSize);

        return $index < $chunks - 1
            ? self::CHUNK_BYTES
            : $totalSize - self::CHUNK_BYTES * ($chunks - 1);
    }

    public function directory(): string
    {
        return self::root().DIRECTORY_SEPARATOR.$this->userId.DIRECTORY_SEPARATOR.$this->uploadId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return $this->readJson(self::META);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function completion(): ?array
    {
        return $this->readJson(self::COMPLETED);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function begin(array $meta): void
    {
        File::ensureDirectoryExists($this->directory(), 0700);
        $this->writeJson(self::META, $meta);
    }

    public function storeChunk(int $index, UploadedFile $chunk): void
    {
        $chunk->move($this->directory(), $this->chunkName($index));
        @touch($this->path(self::META));
    }

    public function hasAllChunks(int $totalChunks): bool
    {
        for ($index = 0; $index < $totalChunks; $index++) {
            if (! is_file($this->path($this->chunkName($index)))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Concatenates the chunks in order, removing each one once copied.
     */
    public function assemble(int $totalChunks): string
    {
        $target = $this->path(self::ASSEMBLED);
        $out = fopen($target, 'wb');
        if ($out === false) {
            throw new \RuntimeException('Could not open the assembled upload for writing.');
        }

        try {
            for ($index = 0; $index < $totalChunks; $index++) {
                $chunkPath = $this->path($this->chunkName($index));
                $in = fopen($chunkPath, 'rb');
                if ($in === false) {
                    throw new \RuntimeException("Missing upload chunk {$index}.");
                }
                stream_copy_to_stream($in, $out);
                fclose($in);
                @unlink($chunkPath);
            }
        } finally {
            fclose($out);
        }

        clearstatcache(true, $target);

        return $target;
    }

    /**
     * Runs $callback while holding an exclusive lock for this upload, or returns null
     * when another request is already finalizing it.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    public function withFinalizeLock(callable $callback): mixed
    {
        File::ensureDirectoryExists($this->directory(), 0700);
        $handle = fopen($this->path('.lock'), 'c');
        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle !== false) {
                fclose($handle);
            }

            return null;
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * True while another request holds the finalize lock (reassembling and storing the file).
     */
    public function isFinalizing(): bool
    {
        $lock = $this->path('.lock');
        if (! is_file($lock)) {
            return false;
        }

        $handle = fopen($lock, 'c');
        if ($handle === false) {
            return false;
        }

        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                return true;
            }
            flock($handle, LOCK_UN);

            return false;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Keeps only a small completion marker so a retried final chunk gets the same result.
     *
     * @param  array<string, mixed>  $payload
     */
    public function markCompleted(array $payload): void
    {
        foreach (File::files($this->directory()) as $file) {
            if (! in_array($file->getFilename(), ['.lock', self::META], true)) {
                @unlink($file->getPathname());
            }
        }
        $this->writeJson(self::COMPLETED, $payload);
    }

    public function discard(): void
    {
        File::deleteDirectory($this->directory());
    }

    public static function activeUploadCount(int $userId): int
    {
        $userDir = self::root().DIRECTORY_SEPARATOR.$userId;
        if (! is_dir($userDir)) {
            return 0;
        }

        $cutoff = time() - self::STALE_SECONDS;
        $count = 0;
        foreach (File::directories($userDir) as $dir) {
            $meta = $dir.DIRECTORY_SEPARATOR.self::META;
            if (! is_file($dir.DIRECTORY_SEPARATOR.self::COMPLETED) && is_file($meta) && filemtime($meta) >= $cutoff) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Deletes the user's unfinished uploads that no chunk has reached for IDLE_SECONDS
     * (interrupted mobile uploads the client will never resume), freeing their slots.
     */
    public static function discardIdle(int $userId, ?int $idleSeconds = null): int
    {
        $userDir = self::root().DIRECTORY_SEPARATOR.$userId;
        if (! is_dir($userDir)) {
            return 0;
        }

        $cutoff = time() - ($idleSeconds ?? self::IDLE_SECONDS);
        $removed = 0;
        foreach (File::directories($userDir) as $dir) {
            if (is_file($dir.DIRECTORY_SEPARATOR.self::COMPLETED)) {
                continue;
            }
            $meta = $dir.DIRECTORY_SEPARATOR.self::META;
            $touched = is_file($meta) ? filemtime($meta) : filemtime($dir);
            if ($touched === false || $touched >= $cutoff) {
                continue;
            }
            if (preg_match(self::UPLOAD_ID_PATTERN, basename($dir)) && (new self($userId, basename($dir)))->isFinalizing()) {
                continue;
            }
            File::deleteDirectory($dir);
            $removed++;
        }

        return $removed;
    }

    /**
     * Deletes upload directories untouched for longer than $olderThanSeconds.
     */
    public static function pruneStale(?int $olderThanSeconds = null): int
    {
        $root = self::root();
        if (! is_dir($root)) {
            return 0;
        }

        $cutoff = time() - ($olderThanSeconds ?? self::STALE_SECONDS);
        $removed = 0;
        foreach (File::directories($root) as $userDir) {
            foreach (File::directories($userDir) as $uploadDir) {
                $meta = $uploadDir.DIRECTORY_SEPARATOR.self::META;
                $touched = is_file($meta) ? filemtime($meta) : filemtime($uploadDir);
                if ($touched !== false && $touched < $cutoff) {
                    File::deleteDirectory($uploadDir);
                    $removed++;
                }
            }
            if (count(File::directories($userDir)) === 0 && count(File::files($userDir)) === 0) {
                @rmdir($userDir);
            }
        }

        return $removed;
    }

    private function chunkName(int $index): string
    {
        return sprintf('chunk_%05d', $index);
    }

    private function path(string $name): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.$name;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $name): ?array
    {
        $path = $this->path($name);
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $name, array $data): void
    {
        file_put_contents($this->path($name), json_encode($data), LOCK_EX);
    }
}
