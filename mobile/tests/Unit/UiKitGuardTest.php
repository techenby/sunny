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
            'cook-tab-{{ strtolower($label) }}' => 'A segmented tab, not a button.',
        ],
        'list-detail' => [
            "list-item-{{ \$item['id'] }}-link" => 'An inline link under a list item, too small for a 48pt button. Recoloured with the row text in a later pass.',
        ],
        'parent-picker-sheet' => [
            '{{ $refPrefix }}-parent-crumb-home' => 'A breadcrumb chip, not a button.',
            "{{ \$refPrefix }}-parent-crumb-{{ \$crumb['id'] }}" => 'A breadcrumb chip, not a button.',
        ],
        'team-settings' => [
            'member-remove' => 'Its confirmation moves to the system alert in a later pass.',
        ],
    ];

    $found = [];
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        $found = [...$found, ...handWrittenButtons($view, $source)];

        preg_match_all('/<(?:native:)?button(?=[\s>\/])/', $source, $buttons, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($buttons as $button) {
            $violations[] = sprintf(
                'resources/views/native/%s.blade.php:%d: <button> is written by hand. Use <x-ui.button> with variant primary, secondary, glass, text or destructive.',
                $view,
                nativeViewLine($source, $button[0][1]),
            );
        }
    }

    $violations = [
        ...$violations,
        ...kitViolations($found, $exceptions, 'this <pressable> holds only a label or an icon, so it is a hand-written button. Use <x-ui.button> with variant primary, secondary, glass, text or destructive (no label makes it a circle, or a row icon for text). If it is not a button, add its ref and the reason to the exceptions in tests/Unit/UiKitGuardTest.php.'),
        ...staleKitExceptions($found, $exceptions),
    ];

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps hand-written empty states out of the views', function () {
    $exceptions = [
        'dashboard' => [
            'recipes-empty' => 'A tappable card with nothing centred.',
            'items-empty' => 'A tappable card with nothing centred and an accent-coloured icon.',
        ],
        'recipe-detail' => [
            'recipe-add-steps' => 'A tappable card with nothing centred and a smaller icon.',
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

it('keeps every kit button to the five looks', function () {
    $looks = ['primary', 'secondary', 'glass', 'text', 'destructive'];
    $props = ['ref', 'variant', 'ios', 'android', 'icon-trailing', 'max-lines', 'tap', 'navigate', 'menu', 'disabled', 'loading', 'a11y-label', 'a11y-hint', 'class'];
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        preg_match_all('/<x-ui\.button\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $source, $buttons, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($buttons as $button) {
            $attributes = rtrim($button[1][0]);
            $hasLabel = ! str_ends_with($attributes, '/');
            $where = sprintf('resources/views/native/%s.blade.php:%d (ref "%s")', $view, nativeViewLine($source, $button[0][1]), nativeTagRef($attributes) ?? 'none');

            preg_match_all('/(?<=\s):?([a-z][\w-]*)(?:="([^"]*)")?/', ' '.rtrim($attributes, '/'), $pairs, PREG_SET_ORDER);

            $given = [];

            foreach ($pairs as $pair) {
                $given[$pair[1]] = $pair[2] ?? '';
            }

            foreach (array_diff(array_keys($given), $props) as $unknown) {
                $violations[] = "{$where}: x-ui.button has no \"{$unknown}\". A look comes whole from its variant: ".implode(', ', $looks).'.';
            }

            if (isset($given['variant']) && ! in_array($given['variant'], $looks, true) && ! str_contains($button[1][0], ':variant=')) {
                $violations[] = "{$where}: \"{$given['variant']}\" is not a button look. Use ".implode(', ', $looks).'.';
            }

            foreach (preg_split('/\s+/', $given['class'] ?? '', -1, PREG_SPLIT_NO_EMPTY) as $class) {
                if (! preg_match('/^(w-full|w-\d+|flex-1|shrink-0|self-(start|center|end)|items-(start|center|end)|p[xlr]-[\d.]+)$/', $class)) {
                    $violations[] = "{$where}: class \"{$class}\" restyles the button. class may only place it (w-full, flex-1, shrink-0, self-*, items-*, side padding on a text button).";
                }
            }

            if (! $hasLabel && ! isset($given['a11y-label'])) {
                $violations[] = "{$where}: a button with no label needs an a11y-label.";
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps delete out of the top bar', function () {
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        preg_match_all('/<native:top-bar-action\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $source, $actions, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($actions as $action) {
            if (preg_match('/(?<![\w-])destructive(?![\w-])|Ios::Trash\b/', $action[1][0]) === 1) {
                $violations[] = sprintf(
                    'resources/views/native/%s.blade.php:%d (ref "%s"): delete does not go in the top bar. Put it last in the screen’s more menu with App\Ui\Menu::delete().',
                    $view,
                    nativeViewLine($source, $action[0][1]),
                    nativeTagRef($action[1][0]) ?? 'none',
                );
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
