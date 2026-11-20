<?php

namespace App\Casts;

use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyThumbnails;
use App\Models\ScanDraft;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** @implements CastsAttributes<string|null, string|null> */
class StoredFilePath implements CastsAttributes
{
    public static function current(?string $path): ?string
    {
        if ($path === null || ! str_starts_with($path, '/')) {
            return $path;
        }

        foreach ([SunnyThumbnails::directory(), SunnyOutbox::photoDirectory(), ScanDraft::photoDirectory()] as $directory) {
            if (dirname($path) !== $directory && str_ends_with(dirname($path), Str::after($directory, storage_path()))) {
                return $directory.'/'.basename($path);
            }
        }

        return $path;
    }

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::current($value);
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }
}
