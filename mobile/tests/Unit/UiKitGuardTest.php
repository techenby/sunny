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

/**
 * @param  array<string, array<string, string>>  $exceptions
 * @return list<array{view: string, line: int, needle: ?string, text: string, ref: ?string}>
 */
function textTagsMatching(string $pattern, array $exceptions, bool $skipGallery = true): array
{
    $found = [];

    foreach (nativeViewSources() as $view => $source) {
        if ($skipGallery && $view === 'ui-kit-gallery') {
            continue;
        }

        preg_match_all('/<(text|x-ui\.heading|x-ui\.text)\b'.NATIVE_TAG_ATTRIBUTES.'>(.*?)<\/\1>/s', $source, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($tags as $tag) {
            if (preg_match($pattern, $tag[2][0]) !== 1) {
                continue;
            }

            $needle = collect(array_keys($exceptions[$view] ?? []))->first(fn (string $needle): bool => str_contains($tag[0][0], $needle));

            $found[] = ['view' => $view, 'line' => nativeViewLine($source, $tag[0][1]), 'needle' => $needle, 'text' => trim($tag[3][0]), 'ref' => nativeTagRef($tag[2][0])];
        }
    }

    return $found;
}

/**
 * @param  list<array{view: string, line: int, needle: ?string, text: string, ref: ?string}>  $found
 * @param  array<string, array<string, string>>  $exceptions
 * @return list<string>
 */
function textTagViolations(array $found, array $exceptions, string $advice): array
{
    $violations = [];

    foreach ($found as $hit) {
        if ($hit['needle'] === null) {
            $violations[] = sprintf('resources/views/native/%s.blade.php:%d ("%s"): %s', $hit['view'], $hit['line'], mb_strimwidth($hit['text'], 0, 40, '…'), $advice);
        }
    }

    foreach ($exceptions as $view => $needles) {
        foreach (array_keys($needles) as $needle) {
            if (! collect($found)->contains(fn (array $hit): bool => $hit['view'] === $view && $hit['needle'] === $needle)) {
                $violations[] = sprintf('%s: "%s" is listed as an exception but no longer matches. Remove it from the list.', $view, $needle);
            }
        }
    }

    return $violations;
}

/**
 * @param  array<string, string>  $chevronExceptions
 * @return list<string>
 */
function sheetViolations(string $view, string $source, array $chevronExceptions = []): array
{
    preg_match_all('/<native:bottom-sheet\b'.NATIVE_TAG_ATTRIBUTES.'>(.*?)<\/native:bottom-sheet>/s', $source, $sheets, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    $violations = [];

    foreach ($sheets as $sheet) {
        $attributes = $sheet[1][0];
        $body = preg_replace('/\{\{--.*?--\}\}/s', '', $sheet[2][0]);
        $where = sprintf('resources/views/native/%s.blade.php:%d (ref "%s")', $view, nativeViewLine($source, $sheet[0][1]), nativeTagRef($attributes) ?? 'none');
        $dismiss = preg_match('/@dismiss="([^"]*)"/', $attributes, $match) ? $match[1] : null;
        $detents = preg_match('/(?<![\w:-])detents="([^"]*)"/', $attributes, $match) ? $match[1] : null;

        preg_match_all('/<x-ui\.sheet-header\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $body, $headers, PREG_SET_ORDER);
        preg_match_all('/<x-ui\.sheet-confirm\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $body, $confirms, PREG_SET_ORDER);
        preg_match_all('/<x-ui\.button\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $body, $buttons, PREG_SET_ORDER);

        if ($dismiss === null) {
            $violations[] = "{$where}: a sheet closes when it is swiped down. Give it @dismiss.";
        }

        if (count($headers) !== 1) {
            $violations[] = "{$where}: a sheet starts with one <x-ui.sheet-header> (the x in a glass circle and the centred title), not a header written by hand.";
        } elseif ($dismiss !== null && preg_match('/(?<![\w:-])close="'.preg_quote($dismiss, '/').'"/', $headers[0][1]) !== 1) {
            $violations[] = "{$where}: the header’s x does what swiping down does. Give <x-ui.sheet-header> close=\"{$dismiss}\".";
        } else {
            $before = substr($body, 0, strpos($body, '<x-ui.sheet-header'));
            $before = preg_replace('/@php\b(?!\s*\().*?@endphp/s', '', $before);
            $before = preg_replace('/@\w+(?:\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', '', $before);

            preg_match_all('/<(?:native:)?([a-z][\w.:-]*)/', $before, $earlier);

            if ($earlier[1] !== ['column']) {
                $violations[] = "{$where}: <x-ui.sheet-header> is the first thing in the sheet’s one column. It paints the sheet’s colour behind everything after it, so anything before it is drawn over.";
            }
        }

        if (preg_match('/(?<!::)\bCancel\b/', $body) === 1) {
            $violations[] = "{$where}: no “Cancel” on a sheet. The x in <x-ui.sheet-header> closes it.";
        }

        if ($dismiss !== null && preg_match('/(?<![\w:-])@?tap="'.preg_quote($dismiss, '/').'"/', $body) === 1) {
            $violations[] = "{$where}: only the x in <x-ui.sheet-header> closes a sheet. Remove the button that calls {$dismiss} (“Cancel”, “Done”, “Not now”).";
        }

        if (count($confirms) > 1) {
            $violations[] = "{$where}: a sheet has one <x-ui.sheet-confirm> at most. Work out its label, icon and tap first, then write it once.";
        }

        foreach ($confirms as $confirm) {
            if (preg_match('/(?<![\w:-]):?disabled(?![\w-])/', $confirm[1]) === 1) {
                $violations[] = "{$where}: the confirm button is unavailable only while saving. Use loading, not disabled.";
            }
        }

        foreach ($buttons as $button) {
            if (preg_match('/(?<![\w:-]):?variant=/', $button[1]) !== 1) {
                $violations[] = sprintf('%s: a Primary button in a sheet is its confirm button (ref "%s"). Use <x-ui.sheet-confirm> at the bottom of the sheet.', $where, nativeTagRef($button[1]) ?? 'none');
            }

            if (preg_match('/(?<![\w:-])variant="destructive"/', $button[1]) === 1) {
                $violations[] = sprintf('%s: a delete is confirmed with the system alert (Dialog::alert), never in a sheet (ref "%s").', $where, nativeTagRef($button[1]) ?? 'none');
            }
        }

        if (! in_array($detents, ['fit', 'large'], true)) {
            $violations[] = "{$where}: a sheet is as tall as its content (detents=\"fit\"), or full height for a long list with a search field (detents=\"large\").";
        } elseif ($detents === 'large' && preg_match('/-leading-icon="(Ios::Magnifyingglass|Android::Search)"/', $body) !== 1) {
            $violations[] = "{$where}: detents=\"large\" is only for a long list with a search field. Use detents=\"fit\".";
        }

        if (preg_match('/\bChevronRight\b/', $body) === 1 && ! isset($chevronExceptions[$view])) {
            $violations[] = "{$where}: a chevron in a sheet means the row opens a deeper level inside the same sheet. If it does, add the view and the reason to the exceptions in tests/Unit/UiKitGuardTest.php.";
        }
    }

    return $violations;
}

it('keeps native list elements out of the views', function () {
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        preg_match_all('/<(?:native:)?(list|list-section|list-item)(?=[\s>\/])/', $source, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($tags as $tag) {
            $violations[] = sprintf(
                'resources/views/native/%s.blade.php:%d: <%s> is a native list element. Use a card (a column with App\Ui\Card::classes()) holding <x-ui.row> or native.inventory-item-row.',
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
            "list-item-{{ \$item['id'] }}-link" => 'An inline link under a list item, too small for a 48pt button.',
        ],
        'parent-picker-sheet' => [
            '{{ $refPrefix }}-parent-crumb-home' => 'A breadcrumb chip, not a button.',
            "{{ \$refPrefix }}-parent-crumb-{{ \$crumb['id'] }}" => 'A breadcrumb chip, not a button.',
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

it('keeps every form on the one template', function () {
    $pinned = ['list-form', 'recipe-form', 'routine-form', 'calendar-feed-form', 'inventory-item-form', 'account', 'change-password', 'create-team', 'invite-member'];
    $labelExceptions = [
        'scan-inventory' => ['scan-count' => 'A count above the list, not a field label.'],
    ];
    $sources = nativeViewSources();
    $violations = [];

    foreach ($pinned as $view) {
        if (! str_contains($sources[$view] ?? '', '<x-ui.submit-bar')) {
            $violations[] = "resources/views/native/{$view}.blade.php: a create or edit form pins its submit button. Use <x-ui.submit-bar> outside the scroll view, and mark the screen with App\\Layouts\\HidesTabBar.";
        }
    }

    foreach ($sources as $view => $source) {
        preg_match_all('/:(?:ios|android)-leading-icon="([^"]*)"/', $source, $icons, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($icons as $icon) {
            if (preg_match('/^(Ios::(Link|Magnifyingglass)|Android::(Link|Search))$/', $icon[1][0]) !== 1) {
                $violations[] = sprintf('resources/views/native/%s.blade.php:%d: %s inside a field says nothing. Only a link or a magnifier goes inside a field; a password field has its own eye.', $view, nativeViewLine($source, $icon[0][1]), $icon[1][0]);
            }
        }

        preg_match_all('/<(?:native:)?button-group(?=[\s>\/])/', $source, $groups, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($groups as $group) {
            $violations[] = sprintf('resources/views/native/%s.blade.php:%d: no segmented controls. Use a <select> for short choices, or cards where the options need explaining.', $view, nativeViewLine($source, $group[0][1]));
        }

        preg_match_all('/<text\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $source, $texts, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($texts as $text) {
            $ref = nativeTagRef($text[1][0]);

            if (preg_match('/class="[^"]*\btext-sm font-semibold text-theme-on-surface-variant\b/', $text[1][0]) === 1 && ! isset($labelExceptions[$view][$ref ?? ''])) {
                $violations[] = sprintf('resources/views/native/%s.blade.php:%d: this looks like a hand-written field label. Use <x-ui.field-label>, or the label attribute of the field itself.', $view, nativeViewLine($source, $text[0][1]));
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps every sheet on the one template', function () {
    $chevronExceptions = [
        'parent-picker-sheet' => 'Its rows drill into places, with a breadcrumb.',
    ];
    $violations = [];
    $withChevrons = [];

    foreach (nativeViewSources() as $view => $source) {
        $violations = [...$violations, ...sheetViolations($view, $source, $chevronExceptions)];

        if (sheetViolations($view, $source) !== sheetViolations($view, $source, [$view => 'allowed'])) {
            $withChevrons[] = $view;
        }
    }

    foreach (array_diff(array_keys($chevronExceptions), $withChevrons) as $view) {
        $violations[] = "{$view}: listed as a sheet with chevrons but it has none. Remove it from the list.";
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

it('catches a sheet that leaves the template', function (string $sheet, string $complaint) {
    $violations = sheetViolations('example', $sheet);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('resources/views/native/example.blade.php:1 (ref "example-sheet")')
        ->and($violations[0])->toContain($complaint);
})->with([
    'a header written by hand' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><row><text font="semibold" class="text-center">Rename</text></row></native:bottom-sheet>',
        'starts with one <x-ui.sheet-header>',
    ],
    'a header that is not first' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.text>Rename</x-ui.text><x-ui.sheet-header title="Rename" close="close" /></column></native:bottom-sheet>',
        'the first thing in the sheet’s one column',
    ],
    'the word Cancel' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Rename" close="close" /><x-ui.button variant="text" tap="keep">Cancel</x-ui.button></column></native:bottom-sheet>',
        'no “Cancel” on a sheet',
    ],
    'a Done button' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Member" close="close" /><x-ui.button variant="text" tap="close">Done</x-ui.button></column></native:bottom-sheet>',
        'only the x in <x-ui.sheet-header> closes a sheet',
    ],
    'an x that does something else' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Rename" close="reset" /></column></native:bottom-sheet>',
        'close="close"',
    ],
    'a Primary button written by hand' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Rename" close="close" /><x-ui.button ref="save" class="w-full" tap="save">Save</x-ui.button></column></native:bottom-sheet>',
        'Use <x-ui.sheet-confirm>',
    ],
    'two confirm buttons' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Share" close="close" /><x-ui.sheet-confirm tap="send">Send</x-ui.sheet-confirm><x-ui.sheet-confirm tap="retry">Try again</x-ui.sheet-confirm></column></native:bottom-sheet>',
        'one <x-ui.sheet-confirm> at most',
    ],
    'a confirm button dimmed until something changes' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Rename" close="close" /><x-ui.sheet-confirm :disabled="$name === $old" tap="save">Save</x-ui.sheet-confirm></column></native:bottom-sheet>',
        'Use loading, not disabled',
    ],
    'a delete confirmed in the sheet' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Member" close="close" /><x-ui.button variant="destructive" tap="remove">Remove Sam</x-ui.button></column></native:bottom-sheet>',
        'confirmed with the system alert',
    ],
    'a half-height sheet' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="medium"><column class="w-full"><x-ui.sheet-header title="Rename" close="close" /></column></native:bottom-sheet>',
        'as tall as its content',
    ],
    'a full-height sheet with no search field' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="large"><column class="w-full"><x-ui.sheet-header title="Rename" close="close" /></column></native:bottom-sheet>',
        'only for a long list with a search field',
    ],
    'a chevron on a row that opens nothing deeper' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><column class="w-full"><x-ui.sheet-header title="Timezone" close="close" /><icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" /></column></native:bottom-sheet>',
        'opens a deeper level inside the same sheet',
    ],
    'no way to swipe it closed' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" detents="fit"><column class="w-full"><x-ui.sheet-header title="Rename" close="close" /></column></native:bottom-sheet>',
        'Give it @dismiss',
    ],
]);

it('keeps coral out of text, except for a selected or active state', function () {
    $exceptions = [
        'cook-recipe' => [
            "\$tab === \$index ? 'bg-theme-primary/15 text-theme-primary-text'" => 'The count on the selected tab.',
            'CookRecipe::clock(' => 'A running timer.',
        ],
        'home' => [
            'uppercase tracking-widest' => 'The brand name above the welcome.',
        ],
        'inventory-item-detail' => [
            'You’re here' => 'Marks the place you are in.',
        ],
        'owner-mark' => [
            "\$owner['initials']" => 'Initials in an owner mark, like an avatar.',
        ],
        'parent-picker-sheet' => [
            '$hereLabel' => 'Marks the place you are in.',
        ],
        'recipe-import-card' => [
            "\$card['initial']" => 'An initial in a monogram tile.',
        ],
        'routine-detail' => [
            'text-theme-primary-text">{{ $loop->iteration }}' => 'The step just moved, in reorder mode.',
        ],
        'today' => [
            '-just-ticked' => 'A step just ticked off.',
        ],
    ];

    $found = textTagsMatching('/text-theme-primary(-text)?(?![\w\/-])/', $exceptions);

    $violations = textTagViolations($found, $exceptions, 'coral is for filled buttons, icons, checkmarks and selected states, not text. An action is dark text with a coral icon (<x-ui.row tone="add">), a text-only action or link is text-theme-secondary in semibold, and a note is <x-ui.note>. If this is a selected or active state, add it and the reason to the exceptions in tests/Unit/UiKitGuardTest.php.');

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps red text for a delete or an error', function () {
    $exceptions = [
        'calendar' => [
            "\$calendarStatus === 'offline' ? '' : 'text-theme-destructive'" => 'The server is unavailable.',
        ],
        'calendar-feed-row' => [
            "\$feed['detail']" => 'Shown only when the calendar failed to load.',
        ],
        'calendar-feeds' => [
            "\$status === 'offline' ? '' : 'text-theme-destructive'" => 'The server is unavailable.',
            'The server couldn’t fetch it' => 'A calendar that failed to load.',
        ],
        'dashboard' => [
            'Download failed' => 'A failed download.',
            'isn’t showing' => 'A calendar that failed to load.',
        ],
        'list-link-error' => [
            '$linkError' => 'A link that could not be read.',
        ],
        'recipe-detail' => [
            'Turn off link' => 'Turns off a shared link, with its icon.',
        ],
        'today' => [
            'isn’t showing' => 'A calendar that failed to load.',
            'The server is unavailable' => 'The server is unavailable.',
        ],
    ];

    $found = array_values(array_filter(
        textTagsMatching('/text-theme-destructive/', $exceptions),
        fn (array $hit): bool => ! str_ends_with($hit['ref'] ?? '', '-error'),
    ));

    $violations = textTagViolations($found, $exceptions, 'red text means a delete or an error. Give an error a ref ending in "-error"; a delete goes in a "more" menu (App\Ui\Menu::delete) or a destructive <x-ui.row>; offline is not an error, so it is dark with a grey icon. If this is an error, add it and the reason to the exceptions in tests/Unit/UiKitGuardTest.php.');

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps red badges and red text colours out of the views', function () {
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        if ($view === 'ui-kit-gallery') {
            continue;
        }

        preg_match_all('/<x-ui\.(badge|text)\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $source, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($tags as $tag) {
            if (preg_match('/(?<![\w:-]):?color="[^"]*\bred\b/', $tag[2][0]) === 1) {
                $violations[] = sprintf('resources/views/native/%s.blade.php:%d: a red x-ui.%s reads as a delete or an error. Use the neutral badge, or color="primary" for a selected state.', $view, nativeViewLine($source, $tag[0][1]), $tag[1][0]);
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

it('puts a row’s actions in one menu, and never a bare trash', function () {
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        preg_match_all('/<x-ui\.button\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $source, $buttons, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $previousEnd = null;

        foreach ($buttons as $button) {
            $attributes = $button[1][0];
            $where = sprintf('resources/views/native/%s.blade.php:%d (ref "%s")', $view, nativeViewLine($source, $button[0][1]), nativeTagRef($attributes) ?? 'none');
            $rowIcon = str_ends_with(rtrim($attributes), '/') && preg_match('/(?<![\w:-])variant="text"/', $attributes) === 1;

            if ($rowIcon && preg_match('/:ios="Ios::Trash"/', $attributes) === 1) {
                $violations[] = "{$where}: a bare trash on a row. Delete goes last in the row’s \"more\" menu (<x-ui.row :menu> with App\\Ui\\Menu::delete).";
            }

            if ($rowIcon && $previousEnd !== null && trim(substr($source, $previousEnd, $button[0][1] - $previousEnd)) === '') {
                $violations[] = "{$where}: two bare icons side by side. A row with two or more actions puts them in one \"more\" menu (<x-ui.row :menu>); reordering is a mode.";
            }

            $previousEnd = $rowIcon ? $button[0][1] + strlen($button[0][0]) : null;
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

it('lets rows draw their own dividers', function () {
    $exceptions = [
        'calendar' => 'Event rows, not on x-ui.row yet.',
        'calendar-feeds' => 'Calendar feed rows, not on x-ui.row yet.',
        'cook-recipe' => 'Ingredient tick rows, not on x-ui.row yet.',
        'create-team' => 'Explainer rows, not on x-ui.row yet.',
        'dashboard' => 'A warning separator and item rows, not on x-ui.row yet.',
        'inventory-item-detail' => 'Starter, child-grid, copies and location rows, not on x-ui.row yet.',
        'inventory-item-form' => 'Extra detail rows hold two text fields.',
        'invite-member' => 'Inviter rows, not on x-ui.row yet.',
        'list-detail' => 'List item rows, not on x-ui.row yet.',
        'parent-picker-sheet' => 'Place picker rows, not on x-ui.row yet.',
        'recently-deleted-inventory' => 'Deleted item rows, not on x-ui.row yet.',
        'recipe-detail' => 'Ingredient, step, copy and share rows, not on x-ui.row yet.',
        'recipe-import-card' => 'A separator inside the import card.',
        'scan-inventory' => 'Scan candidate rows, not on x-ui.row yet.',
        'set-up-home' => 'Setup choice rows, not on x-ui.row yet.',
        'team-settings' => 'Role option rows in the member sheet, not on x-ui.row yet.',
        'today' => 'Event and routine step rows, not on x-ui.row yet.',
    ];

    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        if ($view === 'ui-kit-gallery' || isset($exceptions[$view])) {
            continue;
        }

        preg_match_all('/<divider\b/', $source, $dividers, PREG_OFFSET_CAPTURE);

        foreach ($dividers[0] as $divider) {
            $violations[] = sprintf('resources/views/native/%s.blade.php:%d: <x-ui.row> draws its own divider, starting under its text. For a row with fields in it, use <x-ui.row-divider leading="…">.', $view, nativeViewLine($source, $divider[1]));
        }
    }

    foreach ($exceptions as $view => $reason) {
        if (! str_contains(nativeViewSources()[$view] ?? '', '<divider')) {
            $violations[] = "{$view}: listed as drawing its own dividers but it has none now. Remove it from the list.";
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});

it('keeps offline boxes neutral', function () {
    $violations = [];

    foreach (nativeViewSources() as $view => $source) {
        preg_match_all('/<(column|row)\b'.NATIVE_TAG_ATTRIBUTES.'>\s*<(?:native:)?icon\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $source, $boxes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($boxes as $box) {
            $tinted = preg_match('/\bbg-theme-(primary|destructive|accent)\/\d+/', $box[2][0]) === 1;
            $offlineIcon = str_contains($box[3][0], 'Ios::WifiSlash');
            $switchesForOffline = preg_match("/'offline' \\? 'bg-theme-surface-variant'/", $box[2][0]) === 1;

            if ($tinted && $offlineIcon && ! $switchesForOffline) {
                $violations[] = sprintf('resources/views/native/%s.blade.php:%d (ref "%s"): offline is not an error, so its box is neutral (bg-theme-surface-variant) with a grey icon, not tinted red or coral. Use <x-ui.note tone="offline"> or <x-ui.offline>.', $view, nativeViewLine($source, $box[0][1]), nativeTagRef($box[2][0]) ?? 'none');
            }
        }

        preg_match_all('/<x-ui\.note\b'.NATIVE_TAG_ATTRIBUTES.'>/s', $source, $notes, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($notes as $note) {
            if (str_contains($note[1][0], 'WifiSlash') && preg_match('/(?<![\w-]):?tone=/', $note[1][0]) !== 1) {
                $violations[] = sprintf('resources/views/native/%s.blade.php:%d (ref "%s"): an offline note is <x-ui.note tone="offline">, which is neutral.', $view, nativeViewLine($source, $note[0][1]), nativeTagRef($note[1][0]) ?? 'none');
            }
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
