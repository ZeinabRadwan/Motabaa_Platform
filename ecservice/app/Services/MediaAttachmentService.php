<?php

namespace App\Services;

use App\Models\MediaAttachmentAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaAttachmentService
{
    /**
     * @param  callable(): string  $storagePathResolver  Path after the model exists (includes entity id).
     * @param  callable(string): void  $persistPath  Persist stored basename on the entity column.
     */
    public function upload(
        Model $entity,
        UploadedFile $file,
        string $mediaType,
        string $baseFileName,
        callable $storagePathResolver,
        callable $persistPath,
        array $auditContext = [],
    ): array {
        $correlationId = MediaAttachmentAuditLog::correlationIdFromRequest();
        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType() ?: null;
        $size = $file->getSize() ?: null;

        $this->log([
            'correlation_id' => $correlationId,
            'operation' => 'upload',
            'outcome' => 'attempt',
            'stage' => 'initiated',
            'attachable_type' => $entity::class,
            'attachable_id' => $entity->getKey(),
            'original_filename' => $originalName,
            'mime_type' => $mime,
            'file_size' => $size,
            'meta' => array_merge($auditContext, ['media_type' => $mediaType]),
        ] + $this->contextFromAudit($auditContext));

        $storedBasename = $this->uniqueStoredBasename($baseFileName, $file);
        $path = $storagePathResolver();
        $written = false;

        try {
            saveFile($path, $storedBasename, $file);
            $written = true;

            if (! $this->verifyStoredFile($path, $storedBasename)) {
                throw new \RuntimeException('Stored file verification failed.');
            }

            DB::transaction(function () use ($persistPath, $storedBasename) {
                $persistPath($storedBasename);
            });

            $entity->refresh();

            $payload = [
                'stored_filename' => $storedBasename,
                'storage_path' => $path,
                'attachable_id' => $entity->getKey(),
                'correlation_id' => $correlationId,
            ];

            $this->log([
                'correlation_id' => $correlationId,
                'operation' => 'upload',
                'outcome' => 'success',
                'stage' => 'completed',
                'attachable_type' => $entity::class,
                'attachable_id' => $entity->getKey(),
                'original_filename' => $originalName,
                'stored_filename' => $storedBasename,
                'storage_path' => $path,
                'mime_type' => $mime,
                'file_size' => $size,
                'meta' => array_merge($auditContext, ['media_type' => $mediaType]),
            ] + $this->contextFromAudit($auditContext));

            return $payload;
        } catch (\Throwable $e) {
            if ($written) {
                $this->deleteSingleStoredObject($path, $storedBasename);
            }

            $this->log([
                'correlation_id' => $correlationId,
                'operation' => 'upload',
                'outcome' => 'failure',
                'stage' => $written ? 'post_storage' : 'storage',
                'attachable_type' => $entity::class,
                'attachable_id' => $entity->getKey(),
                'original_filename' => $originalName,
                'stored_filename' => $storedBasename,
                'storage_path' => $path,
                'mime_type' => $mime,
                'file_size' => $size,
                'failure_reason' => Str::limit($e->getMessage(), 2000),
                'meta' => array_merge($auditContext, ['media_type' => $mediaType]),
            ] + $this->contextFromAudit($auditContext));

            throw $e;
        }
    }

    public function deleteAttachableStorage(
        Model $entity,
        callable $storagePathResolver,
        array $auditContext = [],
    ): void {
        $correlationId = MediaAttachmentAuditLog::correlationIdFromRequest();
        $path = $storagePathResolver();

        $this->log([
            'correlation_id' => $correlationId,
            'operation' => 'delete',
            'outcome' => 'attempt',
            'stage' => 'initiated',
            'attachable_type' => $entity::class,
            'attachable_id' => $entity->getKey(),
            'storage_path' => $path,
            'meta' => $auditContext,
        ] + $this->contextFromAudit($auditContext));

        try {
            deleteFile($path);

            $this->log([
                'correlation_id' => $correlationId,
                'operation' => 'delete',
                'outcome' => 'success',
                'stage' => 'storage_removed',
                'attachable_type' => $entity::class,
                'attachable_id' => $entity->getKey(),
                'storage_path' => $path,
                'meta' => $auditContext,
            ] + $this->contextFromAudit($auditContext));
        } catch (\Throwable $e) {
            $this->log([
                'correlation_id' => $correlationId,
                'operation' => 'delete',
                'outcome' => 'failure',
                'stage' => 'storage_remove',
                'attachable_type' => $entity::class,
                'attachable_id' => $entity->getKey(),
                'storage_path' => $path,
                'failure_reason' => Str::limit($e->getMessage(), 2000),
                'meta' => $auditContext,
            ] + $this->contextFromAudit($auditContext));

            throw $e;
        }
    }

    public function uniqueStoredBasename(string $baseFileName, UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $base = pathinfo($baseFileName, PATHINFO_FILENAME);
        if ($base === '') {
            $base = $baseFileName;
        }

        return $base.'_'.str_replace('.', '', uniqid('', true)).'.'.$extension;
    }

    public function verifyStoredFile(string $path, string $storedBasename): bool
    {
        if (usesBunnyStorage()) {
            $found = fetchFiles($path, pathinfo($storedBasename, PATHINFO_FILENAME));

            return is_array($found) && ! empty($found['file_extension']);
        }

        return namedFileAbsolutePath($path, $storedBasename) !== null;
    }

    public function deleteSingleStoredObject(string $path, string $storedBasename): void
    {
        $base = pathinfo($storedBasename, PATHINFO_FILENAME);
        $extension = pathinfo($storedBasename, PATHINFO_EXTENSION);
        if ($base !== '' && $extension !== '') {
            deleteFile($path, $base, $extension);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFromAudit(array $auditContext): array
    {
        return [
            'context_type' => $auditContext['context_type'] ?? null,
            'context_id' => $auditContext['context_id'] ?? null,
            'user_id' => $auditContext['user_id'] ?? auth()->id(),
            'center_id' => $auditContext['center_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function log(array $attributes): void
    {
        try {
            MediaAttachmentAuditLog::record($attributes);
        } catch (\Throwable $e) {
            \Log::warning('media_attachment_audit_log_write_failed', [
                'error' => $e->getMessage(),
                'operation' => $attributes['operation'] ?? null,
            ]);
        }
    }
}
