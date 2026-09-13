<?php
namespace App\Models\FileSystem;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;

class LaravelFileSystem extends BaseFileSystem {

	public function upload($sourceFilePath, $folderPath, $fileName = null)
    {
    	$folderPath = self::fixPath($folderPath);

    	$fileName = ($fileName)?$fileName:pathinfo($sourceFilePath, PATHINFO_BASENAME);

    	Storage::disk('local')->putFileAs($folderPath, new File($sourceFilePath), $fileName);
        
        return true;
    }

    public function list($folderPath, $recursive = false)
    {
        
        return [
        	'files' => Storage::disk('local')->files($folderPath, $recursive),
        	'directories' => Storage::disk('local')->directories($folderPath, $recursive),
        ];
    }

    public function mimeType($filePath) {

    	return Storage::disk('local')->mimeType($filePath);
    }

    public function size($filePath) {

    	return Storage::disk('local')->size($filePath);
    }

    public function get($filePath) {

    	return Storage::disk('local')->get($filePath);
    }

    public function put($filePath, $content) {

    	return Storage::disk('local')->put($filePath, $content);
    }

    public function download($filePath, $force = false, $token = null, $expires = null, $secondsToCache = 3600)
    {
    	$filePath = self::fixPath($filePath);

    	$fileName = pathinfo($filePath, PATHINFO_BASENAME);

    	if(!$force) {
    		
    		$hashableBase = config('motabaa.file_system_key').$filePath.$expires;
	    	$otherToken = md5($hashableBase, true);
	    	$otherToken = base64_encode($otherToken);
	        $otherToken = strtr($otherToken, '+/', '-_');
	        $otherToken = str_replace('=', '', $otherToken);
	        if($otherToken!=$token) return false;
	    }

        $ts = gmdate("D, d M Y H:i:s", time() + $secondsToCache) . " GMT";

    	return Storage::disk('local')->response($filePath, headers:[
    		'Expires' => "$ts",
            'Pragma' => 'cache',
            'Cache-Control' => "max-age=$secondsToCache"
    	]);
    }

    public function secureDownloadURL($filePath)
    {
    	$filePath = self::fixPath($filePath);

        $expires = time() + 3600;
        $hashableBase = config('motabaa.file_system_key').$filePath.$expires;
        $token = md5($hashableBase, true);
        $token = base64_encode($token);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);
        $url = config('app.url')."/download{$filePath}?token={$token}&expires={$expires}";
        return $url;
    }

    public function remove($path)
    {
    	if(Storage::disk('local')->directoryExists($path)) {

    		return Storage::disk('local')->deleteDirectory($path);	
    	}

        return Storage::disk('local')->delete($path);
    }
}