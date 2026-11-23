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
    'the word Cancel' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Rename" close="close" /><x-ui.button variant="text" tap="keep">Cancel</x-ui.button></native:bottom-sheet>',
        'no “Cancel” on a sheet',
    ],
    'a Done button' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Member" close="close" /><x-ui.button variant="text" tap="close">Done</x-ui.button></native:bottom-sheet>',
        'only the x in <x-ui.sheet-header> closes a sheet',
    ],
    'an x that does something else' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Rename" close="reset" /></native:bottom-sheet>',
        'close="close"',
    ],
    'a Primary button written by hand' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Rename" close="close" /><x-ui.button ref="save" class="w-full" tap="save">Save</x-ui.button></native:bottom-sheet>',
        'Use <x-ui.sheet-confirm>',
    ],
    'two confirm buttons' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Share" close="close" /><x-ui.sheet-confirm tap="send">Send</x-ui.sheet-confirm><x-ui.sheet-confirm tap="retry">Try again</x-ui.sheet-confirm></native:bottom-sheet>',
        'one <x-ui.sheet-confirm> at most',
    ],
    'a confirm button dimmed until something changes' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Rename" close="close" /><x-ui.sheet-confirm :disabled="$name === $old" tap="save">Save</x-ui.sheet-confirm></native:bottom-sheet>',
        'Use loading, not disabled',
    ],
    'a delete confirmed in the sheet' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Member" close="close" /><x-ui.button variant="destructive" tap="remove">Remove Sam</x-ui.button></native:bottom-sheet>',
        'confirmed with the system alert',
    ],
    'a half-height sheet' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="medium"><x-ui.sheet-header title="Rename" close="close" /></native:bottom-sheet>',
        'as tall as its content',
    ],
    'a full-height sheet with no search field' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="large"><x-ui.sheet-header title="Rename" close="close" /></native:bottom-sheet>',
        'only for a long list with a search field',
    ],
    'a chevron on a row that opens nothing deeper' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" @dismiss="close" detents="fit"><x-ui.sheet-header title="Timezone" close="close" /><icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" /></native:bottom-sheet>',
        'opens a deeper level inside the same sheet',
    ],
    'no way to swipe it closed' => [
        '<native:bottom-sheet ref="example-sheet" :visible="$open" detents="fit"><x-ui.sheet-header title="Rename" close="close" /></native:bottom-sheet>',
        'Give it @dismiss',
    ],
]);
