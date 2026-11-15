<?php

declare(strict_types=1);

namespace App\Support\Recipes;

final class RecipeLines
{
    /** @return list<array<string, mixed>>|null */
    public static function ingredients(mixed $value): ?array
    {
        $rows = array_map(
            fn (array $row): array => $row['type'] === 'heading' ? $row : [...$row, ...IngredientParser::parse($row['text'])],
            self::rows($value, 'item'),
        );

        return $rows === [] ? null : $rows;
    }

    /** @return list<array<string, mixed>>|null */
    public static function instructions(mixed $value): ?array
    {
        $rows = array_map(
            fn (array $row): array => $row['type'] === 'heading' ? $row : [...$row, 'timers' => TimerParser::parse($row['text'])],
            self::rows($value, 'step'),
        );

        return $rows === [] ? null : $rows;
    }

    public static function text(mixed $value): string
    {
        return implode("\n", array_map(
            fn (array $row): string => $row['type'] === 'heading' ? "# {$row['text']}" : $row['text'],
            self::rows($value, 'item'),
        ));
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     * @return list<array{heading: string|null, start: int, rows: list<array<string, mixed>>}>
     */
    public static function groups(?array $rows): array
    {
        $groups = [];
        $group = null;
        $count = 0;

        foreach ($rows ?? [] as $row) {
            if ($row['type'] === 'heading') {
                if ($group !== null) {
                    $groups[] = $group;
                }

                $group = ['heading' => (string) $row['text'], 'start' => $count + 1, 'rows' => []];

                continue;
            }

            $group ??= ['heading' => null, 'start' => 1, 'rows' => []];
            $group['rows'][] = $row;
            $count++;
        }

        if ($group !== null) {
            $groups[] = $group;
        }

        return $groups;
    }

    /** @return list<string> */
    public static function fromHtml(string $html): array
    {
        return (new HtmlLines)->convert($html);
    }

    /** @return list<array{type: string, text: string}> */
    private static function rows(mixed $value, string $type): array
    {
        if (is_string($value)) {
            $value = preg_match('/<\s*\w+[^>]*>/', $value) === 1
                ? self::fromHtml($value)
                : preg_split('/\R/u', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $entry) {
            $isHeading = is_array($entry) && ($entry['type'] ?? null) === 'heading';
            $text = self::clean(is_array($entry) ? ($entry['text'] ?? '') : $entry);

            if (! $isHeading && preg_match('/^#+\s*(.*)$/su', $text, $matches)) {
                $isHeading = true;
                $text = $matches[1];
            }

            if (! $isHeading) {
                $marker = $type === 'step' ? '/^(?:[-*•]\s+|\d+[.)]\s+)/u' : '/^[-*•]\s+/u';
                $text = preg_replace($marker, '', $text) ?? $text;
            }

            if ($text !== '') {
                $rows[] = ['type' => $isHeading ? 'heading' : $type, 'text' => $text];
            }
        }

        return $rows;
    }

    private static function clean(mixed $text): string
    {
        if (! is_scalar($text)) {
            return '';
        }

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $text) ?? '');
    }
}
