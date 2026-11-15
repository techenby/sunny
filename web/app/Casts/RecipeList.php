<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Recipes\RecipeLines;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<list<array<string, mixed>>|null, mixed> */
class RecipeList implements CastsAttributes
{
    public function __construct(private string $kind) {}

    /** @return list<array<string, mixed>>|null */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value === null ? null : json_decode((string) $value, true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $rows = $this->kind === 'ingredients'
            ? RecipeLines::ingredients($value)
            : RecipeLines::instructions($value);

        return $rows === null
            ? null
            : json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
