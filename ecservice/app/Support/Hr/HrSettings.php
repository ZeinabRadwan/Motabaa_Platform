<?php

namespace App\Support\Hr;

use App\Models\Hr\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HrSettings
{
    /** @var array<int, array<string, mixed>> */
    private static array $cache = [];

    /**
     * Effective flat values ("group.key" => value) for a center: stored overrides merged over defaults.
     */
    public static function all(int $centerId): array
    {
        if (isset(self::$cache[$centerId])) {
            return self::$cache[$centerId];
        }

        $values = HrSettingsRegistry::defaults();
        $stored = Setting::query()->forCenter($centerId)->pluck('value', 'key');
        foreach ($stored as $key => $value) {
            if (HrSettingsRegistry::has($key)) {
                $values[$key] = HrSettingsRegistry::decode($key, $value);
            }
        }

        return self::$cache[$centerId] = $values;
    }

    public static function get(int $centerId, string $key): mixed
    {
        return self::all($centerId)[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>> values grouped as {group: {key: value}}
     */
    public static function grouped(int $centerId): array
    {
        return Arr::undot(self::all($centerId));
    }

    /**
     * Stores the given nested values ({group: {key: value}}). Unknown keys are ignored; every change is
     * audited through the Setting model.
     */
    public static function update(int $centerId, array $nested, ?User $actor = null): array
    {
        $incoming = [];
        foreach (HrSettingsRegistry::definitions() as $key => $definition) {
            if (Arr::has($nested, $key)) {
                $incoming[$key] = Arr::get($nested, $key);
            }
        }

        DB::transaction(function () use ($centerId, $incoming, $actor) {
            $existing = Setting::query()->forCenter($centerId)->lockForUpdate()->get()->keyBy('key');
            foreach ($incoming as $key => $value) {
                $encoded = HrSettingsRegistry::encode($key, $value);
                $row = $existing->get($key) ?? new Setting(['center_id' => $centerId, 'key' => $key]);
                if ($row->exists && $row->value === $encoded) {
                    continue;
                }
                $row->value = $encoded;
                $row->updated_by = $actor?->id;
                $row->save();
            }
        });

        self::forget($centerId);

        return self::grouped($centerId);
    }

    /**
     * Definitions for settings forms (type, default, options), grouped like the values.
     */
    public static function schema(): array
    {
        $schema = [];
        foreach (HrSettingsRegistry::groups() as $group => $keys) {
            foreach ($keys as $key => $definition) {
                $schema[$group][$key] = Arr::only($definition, ['type', 'default', 'options']);
            }
        }

        return $schema;
    }

    public static function forget(?int $centerId = null): void
    {
        if ($centerId === null) {
            self::$cache = [];

            return;
        }

        unset(self::$cache[$centerId]);
    }
}
