<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Obtiene el valor de un setting.
     *
     * El valor se almacena como JSON en la columna `value`.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $raw = DB::table('settings')->where('key', $key)->value('value');

        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $raw;
    }

    /**
     * Crea o actualiza un setting.
     */
    public static function set(string $key, mixed $value): void
    {
        $encoded = json_encode($value);

        $now = now();
        $exists = DB::table('settings')->where('key', $key)->exists();

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            array_filter([
                'value' => $encoded,
                'updated_at' => $now,
                'created_at' => $exists ? null : $now,
            ], fn ($v) => $v !== null),
        );
    }
}
