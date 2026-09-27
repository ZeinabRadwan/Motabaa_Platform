<?php

use GrahamCampbell\ResultType\Success;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use App\Models\System\System;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
if (!function_exists('apiPaginateResponse')) {
    function apiPaginateResponse($data, $items)
    {
        return new JsonResponse([
            'data'  =>  $items,
            'total' =>  $data->total(),
            'perPage' =>  $data->perPage(),
            'currentPage' =>  $data->currentPage(),
            'lastPage' =>  $data->lastPage(),
            'next' =>  $data->nextPageUrl(),
            'previous' =>  $data->previousPageUrl(),
        ], Response::HTTP_OK);
    }
}

if (!function_exists('resolvePerPage')) {
    function resolvePerPage($request, $default = 10, $max = 100)
    {
        $perPage = $default;
        $options = $request->get('options');
        if (is_array($options) && isset($options['itemsPerPage']) && is_numeric($options['itemsPerPage'])) {
            $perPage = (int) $options['itemsPerPage'];
        }

        if ($perPage === -1) {
            return $max;
        }

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }
}

if (!function_exists('resolveSelectLimit')) {
    function resolveSelectLimit($request, $default = 20, $max = 20)
    {
        $limit = $request->get('limit', $default);
        if (!is_numeric($limit)) {
            return $default;
        }

        $limit = (int) $limit;
        if ($limit < 1) {
            return $default;
        }

        return min($limit, $max);
    }
}

if (!function_exists('parseAutocompleteRequest')) {
    function parseAutocompleteRequest($request, $minChars = 0)
    {
        $q = trim((string) $request->get('q', ''));
        $ids = $request->get('ids', []);
        if (is_string($ids)) {
            $ids = preg_split('/\s*,\s*/', $ids, -1, PREG_SPLIT_NO_EMPTY);
        }
        $ids = array_values(array_filter((array) $ids, function ($id) {
            return $id !== null && $id !== '';
        }));

        // Empty query still runs so dropdowns can list options without typing.
        $hasQuery = $q !== '' && mb_strlen($q) >= max((int) $minChars, 1);
        $keywords = $hasQuery ? mb_ereg_replace(" ", "%", getFTS($q)) : '';

        return [
            'run' => true,
            'q' => $q,
            'keywords' => $keywords,
            'ids' => $ids,
            'limit' => resolveSelectLimit($request),
        ];
    }
}

if (!function_exists('apiResponse')) {
    function apiResponse($data, $message = null, $success = true, $code = Response::HTTP_OK)
    {
        return new JsonResponse([
            'data'      =>  $data,
            'success'   =>  $success,
            'message'   =>  $message,

        ], $code);
    }
}

if (!function_exists('vrrors')) {
    function vrrors($validator)
    {

        $errors = [];
        $validators = $validator->messages()->get('*');
        foreach ($validators as $attribute => $messages) {
            foreach ($messages as $message) {
                $error = (object)[];
                $error->code = System::ERROR_FIELD_VALIDATION;
                $error->source = (object)["pointer" => "/data/attributes/$attribute"];
                $error->title = $message;
                $errors[] = $error;
            }
        }

        return response()->json(['errors' => $errors], System::HTTP_SEE_OTHER);
    }
}

if (!function_exists('success')) {
    function success($data = null, $meta = null, $message = null)
    {
        if ($data && $meta) return response()->json(['data' => $data, 'meta' => $meta]);
        $reponse = ['message' => $message, 'status' => 'success', "data" => $data];
        return response()->json($reponse);
    }
}

if (!function_exists('error')) {
    function error($code, $title = null, $attribute = null)
    {

        if (System::isHttpError($code) && empty($title) && empty($attribute)) {

            return response()->json(['errors' => []], $code);
        }

        $error = (object)[];
        $error->code = $code;
        if ($attribute) $error->source = (object)["pointer" => "/data/attributes/$attribute"];
        if ($title) $error->title = $title;

        return response()->json(['errors' => [$error]], System::HTTP_SEE_OTHER);
    }
}

if (!function_exists('errors')) {
    function errors($errors)
    {

        return response()->json(['errors' => $errors], System::HTTP_SEE_OTHER);
    }
}

if (! function_exists('isHasRole')) {
    function isHasRole($isHasRole, $user = null) {
        $user = ($user) ? $user : auth()->user();
        if(!$user) return false;
        foreach ($user->roles as $role) {
            if($isHasRole == $role->default_name){
                return true;
            }       
        }
        return false;
    }
}

if (! function_exists('isParentUser')) {
    /**
     * True only for Spatie parent accounts (default_name "parent" on every assigned role).
     * Any other role — custom job title, empty default_name, staff template — is not parent-only.
     * Does not inspect session permissions; staff UI stays on existing Spatie/CASL checks.
     */
    function isParentUser($user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user || !isHasRole(System::USER_TYPE_PARENT_ROLE_NAME, $user)) {
            return false;
        }

        $roles = $user->roles;
        if ($roles->isEmpty()) {
            return false;
        }

        foreach ($roles as $role) {
            if ($role->default_name !== System::USER_TYPE_PARENT_ROLE_NAME) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('isImpersonating')) {
    function isImpersonating($user = null) {
        $user = ($user) ? $user : auth()->user();
        $token = $user?->currentAccessToken();

        return $token && str_starts_with((string) $token->name, 'impersonation:');
    }
}

if (! function_exists('can')) {
    function can($permission, $user = null) {
        
        $user = ($user) ? $user : auth()->user();
        if(!$user) return false;
        
        return $user->checkPermissionTo($permission, 'web');
    }
}

if (! function_exists('canAll')) {
    function canAll($permissions, $user = null) {
        $user = ($user)?$user:auth()->user();
        if(empty($user)) return false;
        $permissions = collect($permissions)->flatten();
        foreach ($permissions as $permission) {
            if (! $user->hasPermissionTo($permission, 'web')) {
                return false;
            }
        }
        return true;
    }
}

if (! function_exists('canAny')) {
    function canAny($permissions, $user = null) {
        $user = ($user)?$user:auth()->user();
        if(empty($user)) return false;
        $permissions = collect($permissions)->flatten();
        foreach ($permissions as $permission) {
            if ($user->checkPermissionTo($permission, 'web')) {
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('userTime')) {
    function userTime($time, $timezone) {
        if(is_string($time)) {
            $time = new Carbon($time);
        }

        if($timezone) $time->addMinutes(-$timezone);
        return $time;
    }
}

if (! function_exists('cleanPath')) {
    function cleanPath($path) {
        $path = str_replace("\\", "/", $path);
        $path = trim($path, "/");
        while(strrpos($path, "//") !== false) {
            $path = str_replace("//", "/", $path);
        }
        return $path;
    }
}

if (!function_exists('getFTS')) {
    function getFTS($searchText)
    {

        if (filter_var($searchText, FILTER_VALIDATE_EMAIL)) {
            return $searchText;
        }

        $searchText = preg_replace('/[ ]{2,}|[\t]/', ' ', trim($searchText));
        $searchText = preg_replace('/[^\p{L}0-9\s]+/u', ' ', $searchText);
        $patterns = array("/(ا|أ|إ|آ)/", "/(ه|ة)/", "/(ـ)/", "/(ى)/");
        $replacements = array("ا", "ه", "", "ي");
        $searchText = preg_replace($patterns, $replacements, $searchText);

        return $searchText;
    }
}

if (!function_exists('d')) {
    function d($in, $color = "gray")
    {
        if (!config('app.debug')) {
            return;
        }

        dbg($in, "d");
        echo "<div style='background-color:$color; color:white;padding:5px;margin:2px;'>";
        print_r($in);
        echo "</div>";
    }
}

if (!function_exists('em')) {
    function em($in)
    {
        if (!config('app.debug')) {
            return;
        }

        dbg($in, "em");
        echo "<div style='background-color:red; color:white;padding:5px;margin:2px;'>";
        print_r($in);
        echo "</div>";
    }
}

if (!function_exists('sm')) {
    function sm($in)
    {
        if (!config('app.debug')) {
            return;
        }

        dbg($in, "sm");
        echo "<div style='background-color:green; color:white;padding:5px;margin:2px;'>";
        print_r($in);
        echo "</div>";
    }
}

if (!function_exists('wm')) {
    function wm($in)
    {
        if (!config('app.debug')) {
            return;
        }

        dbg($in, "wm");
        echo "<div style='background-color:orange; color:black;padding:5px;margin:2px;'>";
        print_r($in);
        echo "</div>";
    }
}

if (!function_exists('dbg')) {
    function dbg($value, $title = "DEBUG:")
    {
        if (!config('app.debug')) {
            return;
        }

        $safe = $value;
        if (is_array($safe)) {
            foreach (['password', 'password_confirmation', 'token', 'access_token', 'accessToken', 'plainTextToken'] as $key) {
                if (array_key_exists($key, $safe)) {
                    $safe[$key] = '[redacted]';
                }
            }
        }

        \Log::debug($title, is_array($safe) ? $safe : ['value' => $safe]);
    }
}

if (!function_exists('dbgTime')) {
    function dbgTime($message = "", $previousTime = null)
    {
        $time = microtime(true);
        if ($previousTime !== null) {
            $period = $time - $previousTime;
            dbg($period, "Duration ($message):");
        }
        return $time;
    }
}

if (!function_exists('paginate')) {
    function paginate($items, $perPage = 10, $page = null, $options = [])
    {
        $perPage = (int) $perPage;
        if ($perPage === -1) {
            $perPage = 100;
        } elseif ($perPage < 1) {
            $perPage = 10;
        } else {
            $perPage = min($perPage, 100);
        }

        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}

if (!function_exists('applicationUrl')) {
    /**
     * Base URL for API-generated links (signed downloads, etc.).
     * Never returns localhost on LIVE when the request or API_URL override is available.
     */
    function applicationUrl(): string
    {
        $url = rtrim((string) config('app.url'), '/');
        $override = rtrim((string) config('motabaa.api_url', ''), '/');

        if ($override !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $url)) {
            return $override;
        }

        if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $url)) {
            if (!app()->runningInConsole() && request()->getHttpHost()) {
                return request()->getSchemeAndHttpHost();
            }
        }

        return $url;
    }
}

if (!function_exists('usesBunnyStorage')) {
    function usesBunnyStorage($from = null): bool
    {
        if ($from === 'local') {
            return false;
        }

        $storage = strtolower(trim((string) config('motabaa.files.storage')));

        if ($storage === 'local') {
            return false;
        }

        if ($storage === 'bunnycdn') {
            return true;
        }

        // Unset storage driver: prefer Bunny when delivery credentials exist (LIVE default).
        if ($storage === '') {
            return bunnyDeliveryConfigured();
        }

        return false;
    }
}

if (!function_exists('bunnyDeliveryConfigured')) {
    function bunnyDeliveryConfigured(): bool
    {
        return trim((string) config('motabaa.bunny.url')) !== ''
            && trim((string) config('motabaa.bunny.token_key')) !== ''
            && trim((string) config('motabaa.bunny.storage_zone')) !== '';
    }
}

if (!function_exists('bunnyObjectFolder')) {
    function bunnyObjectFolder($folderName): string
    {
        $folder = str_replace('\\', '/', (string) $folderName);
        $folder = ltrim($folder, '/');
        if (str_starts_with($folder, 'public/')) {
            $folder = substr($folder, strlen('public/'));
        }

        return trim($folder, '/');
    }
}

if (!function_exists('sendWhatsAppMessage')) {
    function sendWhatsAppMessage($phone, $message, $url)
    {
        $job = new \App\Jobs\SendWhatsAppMessage($phone, $message, $url);
        if (app()->runningInConsole()) {
            $job->handle();

            return;
        }

        dispatch($job)->afterResponse();
    }
}

if (!function_exists('resolveUploadSourcePath')) {
    function resolveUploadSourcePath($file): ?string
    {
        if ($file instanceof \SplFileInfo) {
            $path = $file->getRealPath() ?: $file->getPathname();

            return ($path && is_readable($path)) ? $path : null;
        }

        if (is_string($file) && is_readable($file)) {
            return $file;
        }

        return null;
    }
}

if (!function_exists('uplaodFileToBunny')) {
    function uplaodFileToBunny($folderName, $fileName, $file)
    {
        $source = resolveUploadSourcePath($file);
        if (!$source) {
            \Log::error('Upload file to bunnycdn failed: source file is not readable', [
                'path' => $folderName.'/'.$fileName,
            ]);

            return false;
        }

        $region = config('motabaa.files.region');  // If German region, set this to an empty string: ''
        $baseHostName = config('motabaa.bunny.base_hostname');
        $hostName = (!empty($region)) ? "{$region}.{$baseHostName}" : $baseHostName;
        $storageZoneName = config('motabaa.bunny.storage_zone');
        $accessKey = config('motabaa.bunny.api_key');

        $url = "https://{$hostName}/{$storageZoneName}/";
        $folderName = bunnyObjectFolder($folderName);
        if($folderName) {
            $url .= "{$folderName}/";
        }
        $url .= str_replace('%2F', '/', rawurlencode($fileName));

        $stream = fopen($source, 'rb');
        if ($stream === false) {
            \Log::error('Upload file to bunnycdn failed: could not open source file', [
                'path' => $folderName.'/'.$fileName,
            ]);

            return false;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_PUT => true,
          CURLOPT_INFILE => $stream,
          CURLOPT_INFILESIZE => filesize($source),
          CURLOPT_TIMEOUT => 600,
          CURLOPT_CONNECTTIMEOUT => 30,
          CURLOPT_HTTPHEADER => [
            "AccessKey: {$accessKey}",
            'Content-Type: application/octet-stream',
          ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($stream);

        $decoded = json_decode((string) $response);
        $bunnyCode = (int) ($decoded->HttpCode ?? $httpCode);
        $ok = $response !== false && $curlError === '' && in_array($bunnyCode, [200, 201], true);

        if (!$ok) {
            \Log::error('Upload file to bunnycdn failed', [
                'http_code' => $httpCode,
                'bunny_code' => $bunnyCode,
                'bunny_message' => $decoded->Message ?? ($curlError !== '' ? $curlError : substr((string) $response, 0, 500)),
                'path' => $folderName.'/'.$fileName,
            ]);
        }

        return $ok;
    }
}

if (!function_exists('fileURLFromBunny')) {
    function fileURLFromBunny($folderName, $fileName)
    {
        $folderName = bunnyObjectFolder($folderName);
        $fileName = ltrim((string) $fileName, '/');
        $path = '/'.$folderName.'/'.$fileName;
        $expires = time() + 3600;
        $hashableBase = config('motabaa.bunny.token_key').$path.$expires;
        $token = md5($hashableBase, true);
        $token = base64_encode($token);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);
        $url = rtrim((string) config('motabaa.bunny.url'), '/')."{$path}?token={$token}&expires={$expires}";
        return $url;
    }
}

if (!function_exists('purgeFileBunny')) {
    function purgeFileBunny($url=null, $async="true")
    {
        $accessKey = config('motabaa.bunny.access_key');
        if($url) {
            $url = "https://api.bunny.net/purge?url={$url}&async={$async}";
        }
        else {
            $pullZoneID = config('motabaa.bunny.pull_zone_id');
            $url = "https://api.bunny.net/pullzone/{$pullZoneID}/purgeCache";
        }

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
            \Log::error("Purge storage from bunnycdn failed: " . $err);
        }
    }
}

if (!function_exists('fetchFilesFromBunny')) {
    function fetchFilesFromBunny($folderName=null, $fileName=null, $getURL=false)
    {
        if($fileName && $getURL) {
            return fileURLFromBunny($folderName, $fileName);
        }
        else {

            $region = config('motabaa.files.region');  // If German region, set this to an empty string: ''
            $baseHostName = config('motabaa.bunny.base_hostname');
            $hostName = (!empty($region)) ? "{$region}.{$baseHostName}" : $baseHostName;
            $storageZoneName = config('motabaa.bunny.storage_zone');
            $accessKey = config('motabaa.bunny.api_key');

            $url = "https://{$hostName}/{$storageZoneName}/";
            $folderName = bunnyObjectFolder($folderName);
            if($folderName) {
                $url .= "{$folderName}/";
            }
            if($fileName) {
                $url .= str_replace('%2F', '/', rawurlencode($fileName));
            }

            $curl = curl_init();
            curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "AccessKey: {$accessKey}",
                "accept: */*"
            ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                \Log::error("Fetch files from bunnycdn failed: " . $err);
            }
            return $response;
        }
    }
}

if (!function_exists('deleteFileFromBunny')) {
    function deleteFileFromBunny($folderName, $fileName=null)
    {
        $region = config('motabaa.files.region');  // If German region, set this to an empty string: ''
        $baseHostName = config('motabaa.bunny.base_hostname');
        $hostName = (!empty($region)) ? "{$region}.{$baseHostName}" : $baseHostName;
        $storageZoneName = config('motabaa.bunny.storage_zone');
        $accessKey = config('motabaa.bunny.api_key');

        $url = "https://{$hostName}/{$storageZoneName}/";
        $folderName = bunnyObjectFolder($folderName);
        if($folderName) {
            $url .= "{$folderName}/";
        }
        if($fileName) {
            $url .= str_replace('%2F', '/', rawurlencode($fileName));
        }

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
        $err = curl_error($curl);
        
        curl_close($curl);

        if ($err) {
            \Log::error("Delete file from bunnycdn failed: " . $err);
        }
    }
}

if (!function_exists('downloadFile')) {
    function downloadFile($fileName, $file)
    {
        $secondsToCache = 500;
        $time = time();
        $ts = gmdate("D, d M Y H:i:s", time() + $secondsToCache) . " GMT";
        if(!file_exists(storage_path('app')."/temp")) mkdir(storage_path('app')."/temp");
        $tempFilePath = storage_path('app')."/temp/$time$fileName";
        file_put_contents($tempFilePath, $file);
        $contentType = mime_content_type($tempFilePath);

        $token = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
            'file_path' => $tempFilePath,
            'file_name' => $fileName,
            'content_type' => $contentType
        ]));

        return route('file.download', ['token'=> $token]);
    }
}

if (!function_exists('bunnyListCacheKey')) {
    function bunnyListCacheKey($path): string
    {
        return 'bunny:list:'.bunnyObjectFolder($path);
    }
}

if (!function_exists('forgetBunnyListCache')) {
    function forgetBunnyListCache($path = null)
    {
        if ($path) {
            \Illuminate\Support\Facades\Cache::forget(bunnyListCacheKey($path));
        }
    }
}

if (!function_exists('fileLookupResetStats')) {
    function fileLookupResetStats()
    {
        $GLOBALS['__file_lookup_stats'] = [
            'listings' => 0,
            'named_cache_hits' => 0,
            'named_cache_misses' => 0,
        ];
    }
}

if (!function_exists('fileLookupStats')) {
    function fileLookupStats()
    {
        return $GLOBALS['__file_lookup_stats'] ?? [
            'listings' => 0,
            'named_cache_hits' => 0,
            'named_cache_misses' => 0,
        ];
    }
}

if (!function_exists('namedFileCacheKey')) {
    function namedFileCacheKey($path, $fileName, $from = null)
    {
        $gen = \Illuminate\Support\Facades\Cache::get('file:gen:'.$path, '0');

        return 'file:named:'.$gen.':'.($from ?: 'default').':'.$path.':'.$fileName;
    }
}

if (!function_exists('forgetRequestFileLookups')) {
    function forgetRequestFileLookups($path = null)
    {
        if ($path === null) {
            $GLOBALS['__file_request_named'] = [];
            $GLOBALS['__file_request_listings'] = [];
            return;
        }

        $suffix = '|'.$path;
        $inner = '|'.$path.'|';
        foreach (array_keys($GLOBALS['__file_request_named'] ?? []) as $key) {
            if (str_contains($key, $inner) || str_ends_with($key, $suffix)) {
                unset($GLOBALS['__file_request_named'][$key]);
            }
        }
        foreach (array_keys($GLOBALS['__file_request_listings'] ?? []) as $key) {
            if (str_ends_with($key, $suffix) || str_contains($key, $inner)) {
                unset($GLOBALS['__file_request_listings'][$key]);
            }
        }
    }
}

if (!function_exists('bumpFileCacheGeneration')) {
    function bumpFileCacheGeneration($path)
    {
        $gen = (string) ((int) \Illuminate\Support\Facades\Cache::get('file:gen:'.$path, '0') + 1);
        \Illuminate\Support\Facades\Cache::put('file:gen:'.$path, $gen, 86400 * 30);
        forgetBunnyListCache($path);
        forgetRequestFileLookups($path);

        return $gen;
    }
}

if (!function_exists('primeNamedFile')) {
    function primeNamedFile($path, $fileName, $from = null)
    {
        if (!$fileName || !str_contains($fileName, '.')) {
            return;
        }

        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($base === '' || $extension === '') {
            return;
        }

        \Illuminate\Support\Facades\Cache::put(namedFileCacheKey($path, $base, $from), [
            'single' => true,
            'file_name' => $base,
            'file_extension' => $extension,
        ], 86400);
    }
}

if (!function_exists('namedFileAbsolutePath')) {
    function namedFileAbsolutePath($path, $basename): ?string
    {
        $basename = basename((string) $basename);
        $relative = trim(str_replace('\\', '/', (string) $path), '/');
        $candidates = [
            storage_path('app/'.$relative).DIRECTORY_SEPARATOR.$basename,
        ];
        if (str_starts_with($relative, 'public/')) {
            $candidates[] = storage_path('app/public/'.substr($relative, strlen('public/'))).DIRECTORY_SEPARATOR.$basename;
        } else {
            $candidates[] = storage_path('app/public/'.$relative).DIRECTORY_SEPARATOR.$basename;
        }

        foreach (array_unique($candidates) as $absolute) {
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return null;
    }
}

if (!function_exists('namedFileRelativePath')) {
    function namedFileRelativePath($path, $basename): string
    {
        return '/'.trim(str_replace('\\', '/', (string) $path), '/').'/'.basename((string) $basename);
    }
}

if (!function_exists('namedFileSecureDownloadUrl')) {
    function namedFileSecureDownloadUrl($path, $basename): string
    {
        $fileSystem = new \App\Models\FileSystem\LaravelFileSystem();

        return $fileSystem->secureDownloadURL(namedFileRelativePath($path, $basename));
    }
}

if (!function_exists('namedFilePublicUrl')) {
    function namedFilePublicUrl($path, $fileName, $fileExtension, $from = null): ?string
    {
        $basename = $fileName.'.'.$fileExtension;
        $localPath = namedFileAbsolutePath($path, $basename);
        $bunnyReady = bunnyDeliveryConfigured();
        $bunnyPrimary = usesBunnyStorage($from);

        // Bunny is the primary store: CDN URL for cloud-only files; signed API download for
        // transitional local copies (uploaded before Bunny was enabled on LIVE).
        if ($bunnyPrimary && $bunnyReady) {
            if ($localPath) {
                return namedFileSecureDownloadUrl($path, $basename);
            }

            return fileURLFromBunny($path, $basename);
        }

        if ($localPath) {
            return namedFileSecureDownloadUrl($path, $basename);
        }

        // Legacy records migrated to Bunny while local disk copy was removed.
        if ($from !== 'local' && $bunnyReady) {
            return fileURLFromBunny($path, $basename);
        }

        return null;
    }
}

if (!function_exists('hydrateNamedFileMeta')) {
    function hydrateNamedFileMeta($path, $fileName, $fileExtension, $from = null)
    {
        $fileURL = namedFilePublicUrl($path, $fileName, $fileExtension, $from);
        if (!$fileURL) {
            return null;
        }

        return [
            'file_name' => $fileName,
            'file_extension' => $fileExtension,
            'file_url' => $fileURL,
        ];
    }
}

if (!function_exists('fileMetaFromStoredName')) {
    function fileMetaFromStoredName($path, $storedName, $from = null)
    {
        if (!is_string($storedName) || $storedName === '') {
            return null;
        }

        $storedName = basename($storedName);
        $base = pathinfo($storedName, PATHINFO_FILENAME);
        $extension = pathinfo($storedName, PATHINFO_EXTENSION);
        if ($base === '' || $extension === '') {
            return null;
        }

        return hydrateNamedFileMeta($path, $base, $extension, $from);
    }
}

if (!function_exists('resolveStoredNamedFile')) {
    /**
     * Build a named-file payload from a stored basename when possible.
     * null = unknown/legacy (list the folder). '' = confirmed missing (skip).
     */
    function resolveStoredNamedFile($path, $knownBaseName, $storedName = null, $from = null)
    {
        if ($storedName === '') {
            return null;
        }

        if (is_string($storedName) && $storedName !== '') {
            $resolved = fileMetaFromStoredName($path, $storedName, $from);
            if ($resolved) {
                return $resolved;
            }
        }

        return fetchFiles($path, $knownBaseName, $from);
    }
}

if (!function_exists('resolveCachedNamedFile')) {
    function resolveCachedNamedFile($path, $cached, $from = null)
    {
        if (!is_array($cached) || !empty($cached['miss'])) {
            return null;
        }

        if (!empty($cached['single'])) {
            return hydrateNamedFileMeta($path, $cached['file_name'], $cached['file_extension'], $from);
        }

        $files = [];
        foreach (($cached['files'] ?? []) as $item) {
            if (empty($item['file_name']) || empty($item['file_extension'])) {
                continue;
            }
            $meta = hydrateNamedFileMeta($path, $item['file_name'], $item['file_extension'], $from);
            if ($meta) {
                $files[] = $meta;
            }
        }

        return count($files) > 0 ? $files : null;
    }
}

if (!function_exists('rememberNamedFileResult')) {
    function rememberNamedFileResult($path, $fileName, $from, $result)
    {
        if (!$fileName) {
            return $result;
        }

        if ($result === null) {
            $payload = ['miss' => true];
            $ttl = 300;
        } elseif (isset($result['file_url'])) {
            $payload = [
                'single' => true,
                'file_name' => $result['file_name'],
                'file_extension' => $result['file_extension'],
            ];
            $ttl = 86400;
        } else {
            $payload = [
                'single' => false,
                'files' => array_map(function ($file) {
                    return [
                        'file_name' => $file['file_name'],
                        'file_extension' => $file['file_extension'],
                    ];
                }, $result),
            ];
            $ttl = 86400;
        }

        \Illuminate\Support\Facades\Cache::put(namedFileCacheKey($path, $fileName, $from), $payload, $ttl);

        return $result;
    }
}

if (!function_exists('saveFile')) {
    function saveFile($path, $fileName, $file, $currentFileURL=null)
    {
        if(usesBunnyStorage()) {
            if($currentFileURL)
                purgeFileBunny($currentFileURL);
            if (!uplaodFileToBunny($path, $fileName, $file)) {
                throw new \RuntimeException('Bunny storage upload failed.');
            }
        }
        else {
            
            if(!str_contains($fileName, '.'))
                $fileName = $fileName.'.'.$file->getClientOriginalExtension();
            $file->move(storage_path('app/'.$path), $fileName);
        }

        bumpFileCacheGeneration($path);
        primeNamedFile($path, $fileName);
    }
}

if (!function_exists('fetchFiles')) {
    function fetchFiles($path, $fileName=null, $from=null)
    {
        if (!isset($GLOBALS['__file_lookup_stats'])) {
            fileLookupResetStats();
        }

        if (!isset($GLOBALS['__file_request_named'])) {
            $GLOBALS['__file_request_named'] = [];
        }
        if (!isset($GLOBALS['__file_request_listings'])) {
            $GLOBALS['__file_request_listings'] = [];
        }

        if ($fileName) {
            $namedKey = ($from ?: 'default').'|'.$path.'|'.$fileName;
            if (array_key_exists($namedKey, $GLOBALS['__file_request_named'])) {
                $GLOBALS['__file_lookup_stats']['named_cache_hits']++;
                return $GLOBALS['__file_request_named'][$namedKey];
            }

            $cached = \Illuminate\Support\Facades\Cache::get(namedFileCacheKey($path, $fileName, $from));
            if ($cached !== null) {
                $GLOBALS['__file_lookup_stats']['named_cache_hits']++;
                return $GLOBALS['__file_request_named'][$namedKey] = resolveCachedNamedFile($path, $cached, $from);
            }

            $GLOBALS['__file_lookup_stats']['named_cache_misses']++;
        }

        $files = [];
        $storageFiles = [];
        $listingKey = ($from ?: 'default').'|'.$path;
        if(!usesBunnyStorage($from)) {
            $GLOBALS['__file_lookup_stats']['listings']++;
            $storageFiles = Storage::files($path);
            if (empty($storageFiles) && bunnyDeliveryConfigured() && $from !== 'local') {
                if (array_key_exists($listingKey, $GLOBALS['__file_request_listings'])) {
                    $storageFiles = $GLOBALS['__file_request_listings'][$listingKey];
                } else {
                    $GLOBALS['__file_lookup_stats']['listings']++;
                    $storageFiles = \Illuminate\Support\Facades\Cache::remember(bunnyListCacheKey($path), 180, function () use ($path) {
                        return json_decode(fetchFilesFromBunny($path)) ?: [];
                    });
                    $GLOBALS['__file_request_listings'][$listingKey] = $storageFiles;
                }
            }
        }
        else if(usesBunnyStorage()) {
            if (array_key_exists($listingKey, $GLOBALS['__file_request_listings'])) {
                $storageFiles = $GLOBALS['__file_request_listings'][$listingKey];
            } else {
                $GLOBALS['__file_lookup_stats']['listings']++;
                $storageFiles = \Illuminate\Support\Facades\Cache::remember(bunnyListCacheKey($path), 180, function () use ($path) {
                    return json_decode(fetchFilesFromBunny($path)) ?: [];
                });
                $GLOBALS['__file_request_listings'][$listingKey] = $storageFiles;
            }
        }

        foreach ($storageFiles as $file) {
            $name = [];
            $fileURL = '';

            if (is_string($file)) {
                $parts = explode('.', basename($file));
                if (!isset($parts[1])) {
                    continue;
                }
                $name = [$parts[0], $parts[1]];
            } elseif (is_object($file) && isset($file->ObjectName)) {
                $parts = explode('.', $file->ObjectName);
                if (!isset($parts[1])) {
                    continue;
                }
                $name = [$parts[0], $parts[1]];
            } else {
                continue;
            }

            $meta = hydrateNamedFileMeta($path, $name[0], $name[1], $from);
            if (!$meta) {
                continue;
            }
            $fileURL = $meta['file_url'];

            if($fileName) {
                if ($fileName == $name[0] || str_replace(' ', '_', $fileName) == $name[0]) {
                    $result = [
                        'file_name' => $name[0], 
                        'file_extension' =>  $name[1], 
                        'file_url' => $fileURL
                    ];
                    $GLOBALS['__file_request_named'][($from ?: 'default').'|'.$path.'|'.$fileName] = $result;
                    return rememberNamedFileResult($path, $fileName, $from, $result);
                }
                else if (Str::contains($name[0], $fileName) || Str::contains($name[0], str_replace(' ', '_', $fileName))) {
                    $files[] = [
                        'file_name' => $name[0], 
                        'file_extension' =>  $name[1], 
                        'file_url' => $fileURL
                    ];
                }
            }
            else {
                $files[] = [
                    'file_name' => $name[0], 
                    'file_extension' =>  $name[1], 
                    'file_url' => $fileURL
                ];
            }
        }

        $result = count($files)>0 ? $files : null;
        if ($fileName) {
            $GLOBALS['__file_request_named'][($from ?: 'default').'|'.$path.'|'.$fileName] = $result;
            return rememberNamedFileResult($path, $fileName, $from, $result);
        }

        return $result;
    }
}

if (!function_exists('deleteFile')) {
    function deleteFile($path, $fileName=null, $extension=null)
    {
        if($fileName) {

            if(!$extension) {

                if(!str_contains($fileName, '.')) {
                    $file = fetchFiles($path, $fileName);
                    if($file)
                        $extension = $file['file_extension'];
                }
            }

            if($extension) {

                if(!str_contains($fileName, '.')) {
                    $fileName = $fileName.'.'.$extension;
                }

                if(usesBunnyStorage()) {
                    deleteFileFromBunny($path, $fileName);
                }
                else {
            
                    $files = Storage::files($path);
                    foreach ($files as $file) {
                        if ($fileName == basename($file)) {
                            Storage::delete($file);
                        }
                    }
                }

                bumpFileCacheGeneration($path);
            }
        }
        else {
            
            if(usesBunnyStorage()) {
                deleteFileFromBunny($path);
            }
            else {
                File::deleteDirectory(storage_path('app/'.$path));
            }

            bumpFileCacheGeneration($path);
        }
    }
}

if (!function_exists('isJson')) {
    function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}

if (!function_exists('formatMultiline')) {
    function formatMultiline($text)
    {
        $lines = explode("\n", $text);
        $text = "";
        foreach ($lines as $line) {
            $text .= "$line<br/>\n";
        }
        return $text;
    }
}