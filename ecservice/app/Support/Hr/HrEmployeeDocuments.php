<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee documents live on the private "local" disk (never publicly addressable) under
 * hr/center_{center}/employees/{employee}/ and are streamed only after the HR permission check.
 */
final class HrEmployeeDocuments
{
    public const DISK = 'local';

    public static function store(Employee $employee, array $data, UploadedFile $file, User $actor): EmployeeDocument
    {
        self::assertTypeMatchesCategory($data['category'], $data['document_type']);

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $directory = "hr/center_{$employee->center_id}/employees/{$employee->id}";
        $path = $file->storeAs($directory, Str::uuid()->toString().'.'.$extension, self::DISK);
        if ($path === false) {
            throw new HrException('document_upload_failed', 'file');
        }

        try {
            return EmployeeDocument::create([
                'center_id' => $employee->center_id,
                'employee_id' => $employee->id,
                'category' => $data['category'],
                'document_type' => $data['document_type'],
                'title' => $data['title'],
                'reference_number' => $data['reference_number'] ?? null,
                'issue_date' => $data['issue_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => $actor->id,
            ]);
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }
    }

    public static function update(EmployeeDocument $document, array $data): EmployeeDocument
    {
        $category = $data['category'] ?? $document->category;
        $type = $data['document_type'] ?? $document->document_type;
        self::assertTypeMatchesCategory($category, $type);

        $document->fill(array_intersect_key($data, array_flip([
            'category', 'document_type', 'title', 'reference_number', 'issue_date', 'expiry_date', 'notes',
        ])));
        $document->save();

        return $document;
    }

    public static function download(EmployeeDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);
        if (! $disk->exists($document->path)) {
            throw new HrException('document_file_missing', 'error', 404);
        }

        return $disk->download($document->path, $document->original_name, array_filter([
            'Content-Type' => $document->mime_type,
        ]));
    }

    private static function assertTypeMatchesCategory(string $category, string $type): void
    {
        if (! in_array($type, EmployeeDocument::TYPES_BY_CATEGORY[$category] ?? [], true)) {
            throw new HrException('document_type_category_mismatch', 'document_type');
        }
    }
}
