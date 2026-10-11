<?php

namespace App\Models\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class PDF
{
    public static function defaultFileName(string $prefix): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        return ExportExcel::safeDownloadFileName($prefix.' '.$timestamp.'.pdf');
    }

    public static function savePdf($view, $data, $fileName, $config = [])
    {
        $tempDir = storage_path('app/temp/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $exportDir = storage_path('app/temp/exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $mergedConfig = array_merge([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'default_font' => 'xbriyaz',
            'temp_dir' => $tempDir,
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ], $config);

        $pdf = \PDF::loadView('pdf.'.$view, compact('data'), [], $mergedConfig);

        $storedName = uniqid('motabaa_pdf_', true).'.pdf';
        $newFilePath = $exportDir.DIRECTORY_SEPARATOR.$storedName;

        $pdf->save($newFilePath);

        if (! File::exists($newFilePath) || File::size($newFilePath) < 1) {
            throw new \RuntimeException('PDF export produced an empty file.');
        }

        $token = Crypt::encryptString(json_encode([
            'file_path' => $newFilePath,
            'file_name' => ExportExcel::safeDownloadFileName($fileName),
            'content_type' => 'application/pdf',
        ]));

        return $token;
    }
}
