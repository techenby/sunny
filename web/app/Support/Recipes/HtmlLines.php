<?php

declare(strict_types=1);

namespace App\Support\Recipes;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class HtmlLines
{
    private const array BLOCKS = ['p', 'div', 'li', 'section', 'article', 'blockquote', 'pre', 'table', 'tr', 'td', 'th'];

    private const int MAX_BOLD_HEADING_LENGTH = 80;

    /** @var list<string> */
    private array $lines = [];

    /** @var list<array{text: string, bold: bool}> */
    private array $buffer = [];

    /** @return list<string> */
    public function convert(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $this->lines = [];
        $this->buffer = [];

        $this->walk($document, bold: false, insideItem: false);
        $this->flush();

        return $this->lines;
    }

    private function walk(DOMNode $node, bool $bold, bool $insideItem): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $this->buffer[] = ['text' => $child->textContent, 'bold' => $bold];

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if ($tag === 'br') {
                $this->flush();
            } elseif (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                $this->flush();
                $this->buffer[] = ['text' => $child->textContent, 'bold' => true];
                $this->flush(heading: true);
            } elseif (in_array($tag, ['ul', 'ol'], true)) {
                $this->flush(heading: $insideItem);
                $this->walk($child, $bold, $insideItem);
                $this->flush();
            } elseif (in_array($tag, self::BLOCKS, true)) {
                $this->flush();
                $this->walk($child, $bold, $insideItem || $tag === 'li');
                $this->flush();
            } else {
                $this->walk($child, $bold || in_array($tag, ['strong', 'b'], true), $insideItem);
            }
        }
    }

    private function flush(bool $heading = false): void
    {
        $text = $this->clean(implode('', array_column($this->buffer, 'text')));
        $plain = $this->clean(implode('', array_column(
            array_filter($this->buffer, fn (array $segment): bool => ! $segment['bold']),
            'text',
        )));

        $this->buffer = [];

        if ($text === '') {
            return;
        }

        $heading = $heading || ($plain === '' && mb_strlen($text) <= self::MAX_BOLD_HEADING_LENGTH);

        $this->lines[] = $heading ? "# {$text}" : $text;
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? '');
    }
}
