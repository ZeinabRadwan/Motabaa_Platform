<?php

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Cache;

class ReferenceCache
{
    public const TTL_SECONDS = 180;

    public static function remember(string $group, string $key, callable $callback, ?int $ttl = null)
    {
        $ttl = $ttl ?? self::TTL_SECONDS;
        $version = (int) Cache::get(self::versionKey($group), 1);

        $stored = Cache::remember(self::entryKey($group, $version, $key), $ttl, function () use ($callback) {
            return ['v' => $callback()];
        });

        return is_array($stored) && array_key_exists('v', $stored) ? $stored['v'] : $stored;
    }

    public static function bump(string $group): void
    {
        $version = (int) Cache::get(self::versionKey($group), 1);
        Cache::forever(self::versionKey($group), $version + 1);
    }

    public static function payload($resource): array
    {
        if ($resource instanceof ResourceCollection || $resource instanceof JsonResource) {
            return json_decode($resource->toJson(), true) ?: [];
        }

        if (is_array($resource)) {
            return $resource;
        }

        return json_decode(json_encode($resource), true) ?: [];
    }

    private static function versionKey(string $group): string
    {
        return "ref:ver:{$group}";
    }

    private static function entryKey(string $group, int $version, string $key): string
    {
        return "ref:{$group}:v{$version}:{$key}";
    }
}
