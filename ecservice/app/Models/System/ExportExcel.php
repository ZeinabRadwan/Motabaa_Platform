<?php

namespace App\Models\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ExportExcel {

    /**
     * Build a safe download filename with a sortable timestamp (no colons).
     */
    public static function defaultFileName(string $prefix): string
    {
        return self::safeDownloadFileName($prefix.' '.Carbon::now()->format('Y-m-d_H-i-s').'.xlsx');
    }

    public static function safeDownloadFileName(string $fileName): string
    {
        $fileName = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '-', $fileName);
        $fileName = trim(preg_replace('/\s+/u', ' ', $fileName));

        return $fileName !== '' ? $fileName : 'export.xlsx';
    }

    public static function saveExcel($excel, $fileName) {

        $storedName = uniqid('motabaa_export_', true).'.xlsx';
        $newFilePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$storedName;

        $binary = Excel::raw($excel, ExcelFormat::XLSX);
        if ($binary === false || $binary === '') {
            throw new \RuntimeException('Excel export produced empty output.');
        }

        if (file_put_contents($newFilePath, $binary) === false) {
            throw new \RuntimeException('Excel export could not write temporary file.');
        }

        $token = Crypt::encryptString(json_encode([
            'file_path' => $newFilePath,
            'file_name' => self::safeDownloadFileName($fileName),
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]));

        return $token;
    }
}
