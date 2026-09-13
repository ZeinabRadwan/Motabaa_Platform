<?php

namespace App\Models\System;

class PDF {    

    public static function savePdf($view, $data, $fileName, $config=[]) {

        $pdf = \PDF::loadView('pdf.'.$view,  compact('data'), [], $config);
        $tempFileName = uniqid() . '.pdf';
        // $pdf->save(storage_path( System::PDF_SAVE_PATH . $tempFileName));
        $tempFilePath = tempnam(sys_get_temp_dir(), $tempFileName);
        $newFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempFileName;
        rename($tempFilePath, $newFilePath);
        $pdf->save($newFilePath);
        $token = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
            'file_path' => $newFilePath,
            'file_name' => $fileName,
            'content_type' => 'application/pdf'
        ]));
        return $token;
    }    

    
}
