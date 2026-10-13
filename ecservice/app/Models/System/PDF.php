<?php

namespace App\Models\System;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class PDF
{
    public const HTML_CHUNK_SEPARATOR = '<!--mpdf-chunk-->';

    public static function defaultFileName(string $prefix): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');

        return ExportExcel::safeDownloadFileName($prefix.' '.$timestamp.'.pdf');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function renderBinary(string $view, array $data, array $config = []): string
    {
        $mergedConfig = self::mergeMpdfConfig($config);

        $viewName = 'pdf.'.$view;
        $html = View::make($viewName, compact('data'))->render();
        $pdf = self::loadHtmlForMpdf($html, $mergedConfig);
        $binary = $pdf->output();

        if ($binary === '' || ! str_starts_with($binary, '%PDF-')) {
            throw new \RuntimeException('PDF export produced invalid output.');
        }

        return $binary;
    }

    public static function downloadResponse(string $binary, string $fileName): \Illuminate\Http\Response
    {
        $safeName = ExportExcel::safeDownloadFileName($fileName);
        if (! str_ends_with(strtolower($safeName), '.pdf')) {
            $safeName .= '.pdf';
        }

        $asciiFallback = preg_replace('/[^\x20-\x7E]/', '_', $safeName) ?: 'export.pdf';
        $disposition = 'attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($safeName);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($binary),
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public static function savePdf($view, $data, $fileName, $config = [])
    {
        $exportDir = self::ensureWritableDirectory(storage_path('app/temp/exports'));
        $binary = self::renderBinary($view, $data, $config);

        $storedName = uniqid('motabaa_pdf_', true).'.pdf';
        $newFilePath = $exportDir.DIRECTORY_SEPARATOR.$storedName;

        if (file_put_contents($newFilePath, $binary) === false) {
            throw new \RuntimeException('PDF export could not write temporary file.');
        }

        $token = Crypt::encryptString(json_encode([
            'file_path' => $newFilePath,
            'file_name' => ExportExcel::safeDownloadFileName($fileName),
            'content_type' => 'application/pdf',
        ]));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function mergeMpdfConfig(array $config): array
    {
        $tempDir = self::ensureWritableDirectory(storage_path('app/temp/mpdf'));
        $published = config('pdf', []);

        $fontDir = $published['custom_font_dir'] ?? base_path('resources/fonts/');
        if (is_string($fontDir) && $fontDir !== '' && ! is_dir($fontDir)) {
            $fontDir = base_path('resources/fonts/');
        }

        $fontData = $published['custom_font_data'] ?? [];
        $defaultFont = 'IBMPlexSansArabic';
        if (! is_array($fontData) || ! array_key_exists($defaultFont, $fontData)) {
            $defaultFont = 'xbriyaz';
        }

        return array_merge([
            'mode' => 'utf-8',
            'format' => $published['format'] ?? 'A4',
            'orientation' => $published['orientation'] ?? 'P',
            'default_font' => $defaultFont,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'temp_dir' => $tempDir,
            'margin_left' => (int) ($published['margin_left'] ?? 10),
            'margin_right' => (int) ($published['margin_right'] ?? 10),
            'margin_top' => (int) ($published['margin_top'] ?? 12),
            'margin_bottom' => (int) ($published['margin_bottom'] ?? 12),
            'custom_font_dir' => $fontDir,
            'custom_font_data' => is_array($fontData) ? $fontData : [],
        ], $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function loadHtmlForMpdf(string $html, array $config)
    {
        if (str_contains($html, self::HTML_CHUNK_SEPARATOR)) {
            return \PDF::chunkLoadHTML(self::HTML_CHUNK_SEPARATOR, $html, $config);
        }

        return \PDF::loadHTML($html, $config);
    }

    private static function ensureWritableDirectory(string $path): string
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException('PDF export could not create directory: '.$path);
        }

        if (! is_writable($path)) {
            throw new \RuntimeException('PDF export directory is not writable: '.$path);
        }

        return $path;
    }
}
