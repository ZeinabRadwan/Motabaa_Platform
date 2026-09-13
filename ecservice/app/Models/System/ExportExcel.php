<?php

namespace App\Models\System;
use Maatwebsite\Excel\Facades\Excel;

class ExportExcel {

    public static function saveExcel($excel, $fileName) {

        $name = uniqid() . '.xlsx';
        $temporaryFilePath = tempnam(sys_get_temp_dir(), $name);
        $newFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name;
        rename($temporaryFilePath, $newFilePath);
        Excel::store($excel, $newFilePath);
        $token = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
            'file_path' => $newFilePath,
            'file_name' => $fileName,
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]));
        return $token;
    }    

    
}
