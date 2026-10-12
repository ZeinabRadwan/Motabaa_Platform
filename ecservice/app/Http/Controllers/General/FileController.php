<?php

namespace App\Http\Controllers\General;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\System\System;


class FileController extends Controller
{
    public function download(Request $request)
    {
        $token = $request->query('token');
        if (! is_string($token) || $token === '') {
            return error(404);
        }

        try {
            $decryptedData = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return error(404);
        }

        if (! is_array($decryptedData) || empty($decryptedData['file_path'])) {
            return error(404);
        }

        $filePath = $decryptedData['file_path'];
        if (file_exists($filePath)) {
            $fileName = $decryptedData['file_name'] ?? 'export.bin';
            $contentType = $decryptedData['content_type'] ?? 'application/octet-stream';
            $asciiFallback = preg_replace('/[^\x20-\x7E]/', '_', $fileName) ?: 'export.bin';
            $disposition = 'attachment; filename="'.$asciiFallback.'"; filename*=UTF-8\'\''.rawurlencode($fileName);

            $headers = [
                'Content-Type' => $contentType,
                'Content-Disposition' => $disposition,
            ];

            return response()->file($filePath, $headers)->deleteFileAfterSend(true);
        }

        return error(404);
    }
}
