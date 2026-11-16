<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<list<array<string, mixed>>|null, mixed> */
class RecipeLines implements CastsAttributes
{
    /** @return list<array{type: string, text: string}> */
    public static function fromText(string $text): array
    {
        return collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->map(fn (string $line): array => preg_match('/^#+\s*(.+)$/u', $line, $matches)
                ? ['type' => 'heading', 'text' => $matches[1]]
                : ['type' => 'item', 'text' => $line])
            ->values()
            ->all();
    }

    /** @param  list<array<string, mixed>>|null  $rows */
    public static function toText(?array $rows): string
    {
        return collect($rows)
            ->map(fn (array $row): string => ($row['type'] ?? null) === 'heading' ? '# '.$row['text'] : $row['text'])
            ->implode("\n");
    }

    /** @return list<array<string, mixed>>|null */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        $rows = $value === null ? null : json_decode((string) $value, true);

        return is_array($rows) ? $rows : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $rows = is_string($value) ? self::fromText($value) : $value;

        return is_array($rows) && $rows !== [] ? json_encode(array_values($rows), JSON_THROW_ON_ERROR) : null;
    }
}
