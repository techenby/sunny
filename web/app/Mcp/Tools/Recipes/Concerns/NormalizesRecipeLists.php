<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Recipes\Concerns;

trait NormalizesRecipeLists
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeRecipeLists(array $data): array
    {
        if (isset($data['ingredients'])) {
            $data['ingredients'] = $this->normalizeListHtml($data['ingredients'], 'ul');
        }

        if (isset($data['instructions'])) {
            $data['instructions'] = $this->normalizeListHtml($data['instructions'], 'ol');
        }

        return $data;
    }

    protected function normalizeListHtml(string $value, string $listTag): string
    {
        if (preg_match('/<\s*\w+[^>]*>/', $value)) {
            return $value;
        }

        $items = collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(fn (string $line) => preg_replace('/^(?:[-*•]+|\d+[.)])\s*/', '', $line))
            ->filter()
            ->map(fn (string $line) => '<li>' . e($line) . '</li>');

        if ($items->isEmpty()) {
            return $value;
        }

        return "<{$listTag}>" . $items->implode('') . "</{$listTag}>";
    }
}
