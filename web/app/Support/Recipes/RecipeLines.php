<?php

declare(strict_types=1);

namespace App\Support\Recipes;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

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
        $document = new DOMDocument;
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $lines = [];
        self::walk($document, $lines);

        return $lines;
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

    /** @param  list<string>  $lines */
    private static function walk(DOMNode $node, array &$lines): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                foreach (preg_split('/\R/u', $child->textContent) ?: [] as $line) {
                    self::push($lines, $line);
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            match (true) {
                in_array($tag, ['ul', 'ol', 'div', 'section', 'blockquote'], true) => self::walk($child, $lines),
                in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b'], true) => self::push($lines, $child->textContent, heading: true),
                $tag === 'li' => self::listItem($child, $lines),
                $tag === 'p' => self::push($lines, $child->textContent, heading: self::isBold($child)),
                default => self::push($lines, $child->textContent),
            };
        }
    }

    /** @param  list<string>  $lines */
    private static function listItem(DOMElement $item, array &$lines): void
    {
        $own = $item->cloneNode(true);
        $nested = [];

        if (! $own instanceof DOMElement) {
            return;
        }

        foreach (['ul', 'ol'] as $tag) {
            foreach (iterator_to_array($own->getElementsByTagName($tag)) as $list) {
                $list->parentNode?->removeChild($list);
            }
        }

        foreach ($item->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['ul', 'ol'], true)) {
                $nested[] = $child;
            }
        }

        self::push($lines, $own->textContent, heading: $nested !== [] || self::isBold($own));

        foreach ($nested as $list) {
            self::walk($list, $lines);
        }
    }

    private static function isBold(DOMElement $element): bool
    {
        $bold = '';

        foreach (['strong', 'b'] as $tag) {
            foreach ($element->getElementsByTagName($tag) as $node) {
                $bold .= $node->textContent;
            }
        }

        return self::clean($bold) !== '' && self::clean($bold) === self::clean($element->textContent);
    }

    /** @param  list<string>  $lines */
    private static function push(array &$lines, string $text, bool $heading = false): void
    {
        $text = self::clean($text);

        if ($text !== '') {
            $lines[] = $heading ? "# {$text}" : $text;
        }
    }
}
