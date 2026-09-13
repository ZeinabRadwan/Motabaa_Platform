<?php
namespace App\Models\FileSystem;

class BunnyFileSystem extends BaseFileSystem {
	
	public function purge($folderName = null, $fileName = null, $async = "true")
    {
        $url = "";
        if($folderName && $fileName) {
            $path = '/'.$folderName.'/'.$fileName;
            $url = config('motabaa.bunny.url')."/{$path}";
            $url = "https://api.bunny.net/purge?url={$url}&async={$async}";
        }
        else {
            $pullZoneID = config('motabaa.bunny.pull_zone_id');
            $url = "https://api.bunny.net/pullzone/{$pullZoneID}/purgeCache";
        }

        $accessKey = config('motabaa.bunny.access_key');

        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_HTTPHEADER => [
            "AccessKey: {$accessKey}"
          ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            
            \Log::error("BunnyFileSystem purgeURL failed: " . $err);
            return false;
        }

        return true;
    }

    public function url($folderPath = null, $fileName = null, $filePath = null) {

        $region = config('motabaa.files.region');  // If German region, set this to an empty string: ''
        $baseHostName = config('motabaa.bunny.base_hostname');
        $hostName = (!empty($region)) ? "{$region}.{$baseHostName}" : $baseHostName;
        $storageZoneName = config('motabaa.bunny.storage_zone');

        $url = "https://{$hostName}/{$storageZoneName}";
        
        if($filePath) {
            $url .= "{$filePath}";
        }

        if($folderPath) {
            $url .= "{$folderPath}/";
        }
        
        if($fileName) {
            $url .= urlencode($fileName);
        }

        return $url;
    }

    public static function check($curl, $response) {

        $response = json_decode($response);

        if(!empty($response)) {

            $response = (object)$response;
            
            if(isset($response->HttpCode) && !in_array($response->HttpCode, [200, 201])) {
                
                $error = (object)[];
                $error->code = $response->HttpCode;
                $error->message = $response->Message;

                return $error;
            }
        }

        $errorMessage = curl_error($curl);

        if(!empty($errorMessage)) {

            $error = (object)[];
            $error->code = 0;
            $error->message = $errorMessage;

            return $error;
        }

        return null;
    }
    
    public function upload($sourceFilePath, $folderPath, $fileName = null)
    {
        $fileName = ($fileName)?$fileName:pathinfo($sourceFilePath, PATHINFO_BASENAME);

        $folderPath = self::fixPath($folderPath);

        $url = self::url(folderPath: $folderPath, fileName: $fileName);

        self::purge($folderPath, $fileName);

        $accessKey = config('motabaa.bunny.api_key');

        $curl = curl_init();

        $options = array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_PUT => true,
          CURLOPT_INFILE => fopen($sourceFilePath, 'r'),
          CURLOPT_INFILESIZE => filesize($sourceFilePath),
          CURLOPT_HTTPHEADER => array(
            "AccessKey: {$accessKey}",
            'Content-Type: application/octet-stream'
          )
        );

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $error = self::check($curl, $response);
        curl_close($curl);

        if ($error) {

            \Log::error("BunnyFileSystem upload failed: " . $error->message);
            return false;
        }

        return true;
    }

    public function list($folderPath)
    {
        $folderPath = self::fixPath($folderPath);

        $url = self::url(folderPath: $folderPath);

        $accessKey = config('motabaa.bunny.api_key');

        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "AccessKey: {$accessKey}",
            "accept: */*"
        ],
        ]);

        $response = curl_exec($curl);
        $error = self::check($curl, $response);
        curl_close($curl);

        if ($error) {

            \Log::error("BunnyFileSystem list failed: " . $error->message);
            return false;
        }

        return json_decode($response);
    }

    public function get($filePath)
    {
        $url = self::url(filePath: $filePath);

        $accessKey = config('motabaa.bunny.api_key');

        $curl = curl_init();
        curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "AccessKey: {$accessKey}",
            "accept: */*"
        ],
        ]);

        $response = curl_exec($curl);
        $error = self::check($curl, $response);
        curl_close($curl);

        if ($error) {

            \Log::error("BunnyFileSystem get failed: " . $error->message);
            return false;
        }

        return $response;
    }

    public function secureDownloadURL($filePath)
    {
        $filePath = self::fixPath($filePath);

        $expires = time() + 3600;
        $hashableBase = config('motabaa.bunny.token_key').$filePath.$expires;
        $token = md5($hashableBase, true);
        $token = base64_encode($token);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);
        $url = config('motabaa.bunny.url')."{$filePath}?token={$token}&expires={$expires}";
        return $url;
    }

    public function remove($filePath)
    {
        $url = self::url(filePath: $filePath);

        $accessKey = config('motabaa.bunny.api_key');

        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "DELETE",
          CURLOPT_HTTPHEADER => [
            "AccessKey: {$accessKey}"
          ],
        ]);
        
        $response = curl_exec($curl);
        $error = self::check($curl, $response);
        curl_close($curl);

        if ($error) {

            \Log::error("BunnyFileSystem remove failed: " . $error->message);
            return false;
        }

        return true;
    }
	
}