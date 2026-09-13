<?php

namespace App\Http\Controllers\General;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\System\System;


class ExcelController extends Controller
{
    public function download(Request $request, $token){

        $decryptedData = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token), true);
        // $filePath = storage_path(System::PDF_SAVE_PATH . $decryptedData['file_name']);
        $filePath = $decryptedData['file_name'];
        if (file_exists($filePath)) {
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $decryptedData['default_name'] . '"',
            ];
            return response()->file($filePath, $headers)->deleteFileAfterSend(false);
        } else {
            return error(404);
        }

    }
}
