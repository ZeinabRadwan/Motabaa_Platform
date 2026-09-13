<?php
namespace App\Models\FileSystem;

class BaseFileSystem {
	
	public static function fixPath($path) {

		$path = str_replace('\\', '/', $path);
    	$path = (strpos($path, "/")===0)?$path:"/$path";
    	return $path;
	}
    
    public function upload($sourceFilePath, $folderPath, $fileName = null)
    {
        return false;
    }

    public function list($folderPath)
    {
        return false;
    }

    public function get($filePath)
    {
        return false;
    }

    public function mimeType($filePath)
    {
        return false;
    }

    public function size($filePath)
    {
        return false;
    }

    public function download($filePath)
    {
        return redirect($this->secureDownloadURL($filePath));
    }

    public function secureDownloadURL($filePath)
    {
        return false;
    }

    public function remove($filePath)
    {
        return false;
    }
}