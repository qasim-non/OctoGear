<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\DB;

/**
 * Seeder-only natural-key lookups. Never infer IDs from insertion order.
 * Run seeders as one deployment job, not concurrently.
 */
final class SeedRecords
{
    public static function reference(string $table, array $identity, array $values = [], string $key = 'id'): int
    {
        $row = DB::table($table)->where($identity)->orderBy($key)->first();
        if ($row) {
            unset($values['created_at'], $values['updated_at'], $values['deleted_at']);
            $changed = array_filter($values, fn ($value, $column) => (string) $row->{$column} !== (string) $value, ARRAY_FILTER_USE_BOTH);
            if ($changed !== []) {
                DB::table($table)->where($key, $row->{$key})->update([...$changed, 'updated_at' => now()]);
            }

            return (int) $row->{$key};
        }

        return (int) DB::table($table)->insertGetId([
            ...$identity, ...$values,
            'created_at' => $values['created_at'] ?? now(),
            'updated_at' => $values['updated_at'] ?? now(),
        ], $key);
    }

    /** Preserve operator-managed settings/content and edited demo records on reruns. */
    public static function once(string $table, array $identity, array $values = [], string $key = 'id'): int
    {
        $existing = DB::table($table)->where($identity)->orderBy($key)->value($key);

        return $existing !== null ? (int) $existing : self::reference($table, $identity, $values, $key);
    }
}
