<?php

const NATIVE_TAG_ATTRIBUTES = '((?:[^>"\']*+(?:"[^"]*+"|\'[^\']*+\')?)*+)';

/**
 * @return array<string, string>
 */
function nativeViewSources(): array
{
    $root = dirname(__DIR__, 2).'/resources/views/native/';
    $sources = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_ends_with($file->getPathname(), '.blade.php')) {
            $sources[substr($file->getPathname(), strlen($root), -strlen('.blade.php'))] = file_get_contents($file->getPathname());
        }
    }

    ksort($sources);

    return $sources;
}

function nativeViewLine(string $source, int $offset): int
{
    return substr_count($source, "\n", 0, $offset) + 1;
}

function nativeTagRef(string $attributes): ?string
{
    return preg_match('/(?<![\w:-])ref="([^"]*)"/', $attributes, $match) ? $match[1] : null;
}

/**
 * @return list<array{view: string, line: int, ref: ?string}>
 */
function handWrittenButtons(string $view, string $source): array
{
    preg_match_all('/<pressable\b'.NATIVE_TAG_ATTRIBUTES.'>(.*?)<\/pressable>/s', $source, $pressables, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    $found = [];

    foreach ($pressables as $pressable) {
        $body = preg_replace('/\{\{--.*?--\}\}/s', '', $pressable[2][0]);
        $body = preg_replace('/@\w+(?:\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', '', $body);

        preg_match_all('/<(?:native:)?([a-z][\w.:-]*)\b'.NATIVE_TAG_ATTRIBUTES.'\/?>/s', $body, $tags, PREG_SET_ORDER);

        $names = array_count_values(array_column($tags, 1));
        $fullWidthRow = collect($tags)->contains(fn (array $tag): bool => $tag[1] === 'row' && preg_match('/\bw-full\b/', $tag[2]) === 1);

        $isButton = $tags !== []
            && array_diff(array_keys($names), ['row', 'icon', 'text']) === []
            && ($names['row'] ?? 0) <= 1
            && ($names['text'] ?? 0) <= 2
            && ! $fullWidthRow;

        if ($isButton) {
            $found[] = ['view' => $view, 'line' => nativeViewLine($source, $pressable[0][1]), 'ref' => nativeTagRef($pressable[1][0])];
        }
    }

    return $found;
}

/**
 * @return list<array{view: string, line: int, ref: ?string}>
 */
function handWrittenEmptyStates(string $view, string $source): array
{
    $pattern = '/<(column|pressable)\b'.NATIVE_TAG_ATTRIBUTES.'>\s*'
        .'<(?:native:)?icon\b'.NATIVE_TAG_ATTRIBUTES.'>\s*'
        .'<x-ui\.heading\b'.NATIVE_TAG_ATTRIBUTES.'>.*?<\/x-ui\.heading>\s*'
        .'<x-ui\.text\b'.NATIVE_TAG_ATTRIBUTES.'>.*?<\/x-ui\.text>\s*'
        .'<\/\1>/s';

    preg_match_all($pattern, $source, $cards, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    $found = [];

    foreach ($cards as $card) {
        if (str_contains($card[2][0], 'Card::classes()')) {
            $found[] = ['view' => $view, 'line' => nativeViewLine($source, $card[0][1]), 'ref' => nativeTagRef($card[2][0])];
        }
    }

    return $found;
}

/**
 * @param  list<array{view: string, line: int, ref: ?string}>  $found
 * @param  array<string, array<string, string>>  $exceptions
 * @return list<string>
 */
function kitViolations(array $found, array $exceptions, string $advice): array
{
    return collect($found)
        ->reject(fn (array $hit): bool => $hit['ref'] !== null && isset($exceptions[$hit['view']][$hit['ref']]))
        ->map(fn (array $hit): string => sprintf('resources/views/native/%s.blade.php:%d (ref "%s"): %s', $hit['view'], $hit['line'], $hit['ref'] ?? 'none', $advice))
        ->values()
        ->all();
}

/**
 * @param  list<array{view: string, line: int, ref: ?string}>  $found
 * @param  array<string, array<string, string>>  $exceptions
 * @return list<string>
 */
function staleKitExceptions(array $found, array $exceptions): array
{
    $stale = [];

    foreach ($exceptions as $view => $refs) {
        foreach (array_keys($refs) as $ref) {
            if (! collect($found)->contains(fn (array $hit): bool => $hit['view'] === $view && $hit['ref'] === $ref)) {
                $stale[] = sprintf('%s: "%s" is listed as an exception but is no longer hand-written. Remove it from the list.', $view, $ref);
            }
        }
    }

    return $stale;
}

it('keeps native list elements out of the views', function () {
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        preg_match_all('/<(?:native:)?(list|list-section|list-item)(?=[\s>\/])/', $source, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($tags as $tag) {
            $violations[] = sprintf(
                'resources/views/native/%s.blade.php:%d: <%s> is a native list element. Use a card (a column with App\Ui\Card::classes()) holding native.owned-row, native.inventory-item-row or native.settings-row.',
                $view,
                nativeViewLine($source, $tag[0][1]),
                $tag[1][0],
            );
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps hand-written buttons out of the views', function () {
    $exceptions = [
        'cook-recipe' => [
            'cook-timer-stop' => 'A filled pill with a running clock beside the label. The only one.',
            'cook-step-{{ $number }}-timer' => 'A coral-outlined pill with a clock beside the label. The only one.',
            'cook-tab-{{ strtolower($label) }}' => 'A segmented tab, not a button.',
        ],
        'inventory-selection-bar' => [
            'selection-delete' => 'A red tint pill. The only one.',
        ],
        'list-detail' => [
            "list-item-{{ \$item['id'] }}-link" => 'An inline link under a list item, not a button.',
        ],
        'parent-picker-sheet' => [
            '{{ $refPrefix }}-parent-crumb-home' => 'A breadcrumb chip, not a button.',
            "{{ \$refPrefix }}-parent-crumb-{{ \$crumb['id'] }}" => 'A breadcrumb chip, not a button.',
        ],
        'photo-field' => [
            '{{ $refPrefix }}-photo-remove' => 'A text action with a fixed height and a class-based weight. Converting it changes its size and weight.',
        ],
        'queued-change' => [
            '{{ $refPrefix }}-sync-discard' => 'A text action with a fixed height and a class-based weight. Converting it changes its size and weight.',
        ],
        'recently-deleted-inventory' => [
            "deleted-forever-{{ \$item['id'] }}" => 'An outlined pill with no fill and red text. The only one.',
        ],
        'scan-inventory' => [
            "scan-candidate-{{ \$candidate['id'] }}-use-suggestion" => 'A text action with a fixed height and a class-based weight. Converting it changes its size and weight.',
        ],
        'team-settings' => [
            'member-remove' => 'A bordered rectangle with a 24pt radius and red text. The only one.',
        ],
        'today' => [
            'day-retry' => 'A surface-coloured pill with no border. The only one.',
        ],
    ];

    $found = [];
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        $found = [...$found, ...handWrittenButtons($view, $source)];

        preg_match_all('/<(?:native:)?button(?=[\s>\/])/', $source, $buttons, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($buttons as $button) {
            $violations[] = sprintf(
                'resources/views/native/%s.blade.php:%d: <button> is written by hand. Use <x-ui.button> with variant primary, secondary, destructive or ghost.',
                $view,
                nativeViewLine($source, $button[0][1]),
            );
        }
    }

    $violations = [
        ...$violations,
        ...kitViolations($found, $exceptions, 'this <pressable> holds only a label or an icon, so it is a hand-written button. Use <x-ui.button> with variant filled, tint, neutral, glass, outline, outline-pill or plain (no label makes it an icon button). If it is not a button, or its look exists nowhere else, add its ref and the reason to the exceptions in tests/Unit/UiKitGuardTest.php.'),
        ...staleKitExceptions($found, $exceptions),
    ];

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps hand-written empty states out of the views', function () {
    $exceptions = [
        'calendar' => [
            'calendar-nothing' => 'Its heading is not centred, so it would change if it wrapped onto two lines.',
        ],
        'calendar-feeds' => [
            'calendar-feeds-empty' => 'Its heading is not centred, so it would change if it wrapped onto two lines.',
        ],
        'dashboard' => [
            'recipes-empty' => 'A tappable card with nothing centred.',
            'items-empty' => 'A tappable card with nothing centred and an accent-coloured icon.',
        ],
        'recipe-detail' => [
            'recipe-add-steps' => 'A tappable card with nothing centred and a smaller icon.',
        ],
        'today' => [
            'routines-empty' => 'Its heading is not centred, so it would change if it wrapped onto two lines.',
        ],
    ];

    $found = [];

    foreach (nativeViewSources() as $view => $source) {
        $found = [...$found, ...handWrittenEmptyStates($view, $source)];
    }

    $violations = [
        ...kitViolations($found, $exceptions, 'this card holds only an icon, a heading and a line of text, so it is a hand-written empty state. Use <x-ui.empty-state> with ios, android, heading and text. If it has to differ, add its ref and the reason to the exceptions in tests/Unit/UiKitGuardTest.php.'),
        ...staleKitExceptions($found, $exceptions),
    ];

    expect($violations)->toBe([], implode("\n", $violations));
});
