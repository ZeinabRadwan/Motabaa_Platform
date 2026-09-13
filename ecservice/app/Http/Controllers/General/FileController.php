<?php

namespace App\Http\Controllers\General;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\System\System;


class FileController extends Controller
{
    public function download(Request $request, $token){

        $decryptedData = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token), true);
        // $filePath = storage_path(System::PDF_SAVE_PATH . $decryptedData['file_path']);
        $filePath = $decryptedData['file_path'];
        if (file_exists($filePath)) {
            $headers = [
                'Content-Type' => $decryptedData['content_type'],
                'Content-Disposition' => 'attachment; filename="' . $decryptedData['file_name'] . '"',
            ];
            return response()->file($filePath, $headers)->deleteFileAfterSend(true);
        } else {
            return error(404);
        }

    }
}
