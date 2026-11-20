<?php

use Illuminate\Support\Facades\View;
use Native\Mobile\Testing\Native;
use Tests\Fixtures\UiKitScreen;

beforeEach(function () {
    View::addLocation(base_path('tests/Fixtures/views'));
});

function refNode(string $ref): Closure
{
    return fn (array $node): bool => ($node['ref'] ?? null) === $ref;
}

it('renders kit components in place among their siblings', function () {
    Native::test(UiKitScreen::class)
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'screen'
            && array_column($node['children'], 'ref') === [
                'before',
                'heading',
                'heading-xl',
                'heading-destructive',
                'subheading',
                'text',
                'text-strong',
                'text-red',
                'badge',
                'avatar-icon',
                'avatar-photo',
                'avatar-initials',
                'button-primary',
                'button-secondary',
                'button-glass',
                'button-destructive',
                'button-text',
                'button-disabled',
                'button-loading',
                'button-circle',
                'button-row-icon',
                'button-glass-disabled',
                'button-row-icon-disabled',
                'empty-state',
                'after',
            ]);
});

it('styles headings like flux headings', function () {
    Native::test(UiKitScreen::class)
        ->assertElement('text', fn (array $node): bool => refNode('heading')($node)
            && $node['props']['text'] === 'Camping gear'
            && $node['props']['font_size'] == 16
            && $node['props']['font_name'] === 'medium'
            && $node['props']['color'] === theme('on-surface'))
        ->assertElement('text', fn (array $node): bool => refNode('heading-xl')($node)
            && $node['props']['font_size'] == 24
            && $node['props']['color'] === theme('primary-text'))
        ->assertElement('text', fn (array $node): bool => refNode('heading-destructive')($node)
            && $node['props']['color'] === theme('destructive'));
});

it('styles subheadings and text like flux', function () {
    Native::test(UiKitScreen::class)
        ->assertElement('text', fn (array $node): bool => refNode('subheading')($node)
            && $node['props']['font_size'] == 16
            && $node['props']['color'] === theme('on-surface-variant'))
        ->assertElement('text', fn (array $node): bool => refNode('text')($node)
            && $node['props']['font_size'] == 14
            && $node['props']['max_lines'] == 1
            && $node['props']['color'] === theme('on-surface-variant'))
        ->assertElement('text', fn (array $node): bool => refNode('text-strong')($node)
            && $node['props']['color'] === theme('on-surface'))
        ->assertElement('text', fn (array $node): bool => refNode('text-red')($node)
            && $node['props']['color'] === '#DC2626'
            && $node['props']['dark_color'] === '#F87171');
});

it('styles badges like flux badges', function () {
    Native::test(UiKitScreen::class)
        ->assertElement('text', fn (array $node): bool => refNode('badge')($node)
            && $node['props']['text'] === 'Shopping'
            && $node['props']['font_size'] == 12
            && $node['props']['color'] === '#3F6212'
            && $node['style']['border_radius'] == 6
            && $node['layout']['padding'] == [4, 8, 4, 8]);
});

it('shows an avatar’s icon, photo, or initials', function (string $platform, string $iconName) {
    Native::test(UiKitScreen::class, platform: $platform)
        ->assertElement('column', fn (array $node): bool => refNode('avatar-icon')($node)
            && $node['layout']['width'] == 32
            && $node['style']['bg_color'] === '#FDE68A'
            && $node['children'][0]['props']['name'] === $iconName
            && $node['children'][0]['props']['color'] === '#92400E')
        ->assertElement('image', fn (array $node): bool => refNode('avatar-photo')($node)
            && $node['props']['src'] === 'https://example.com/photo.jpg'
            && $node['props']['alt'] === 'Photo of tent'
            && $node['layout']['width'] == 32)
        ->assertElement('column', fn (array $node): bool => refNode('avatar-initials')($node)
            && $node['layout']['min_width'] == 40 && $node['layout']['min_height'] == 40
            && $node['style']['border_radius'] == 9999
            && $node['props']['a11y_label'] === 'Camping gear'
            && $node['children'][0]['props']['text'] === 'CG');
})->with([
    'ios' => ['ios', 'cube'],
    'android' => ['android', 'view_in_ar'],
]);

it('gives cards a flux card surface', function () {
    Native::test(UiKitScreen::class)
        ->assertElement('column', fn (array $node): bool => refNode('screen')($node)
            && $node['style']['bg_color'] === theme('surface')
            && $node['style']['border_color'] === '#1A0B0808'
            && $node['style']['border_radius'] == 18);
});

it('draws each filled look from the theme, 48pt tall and fully rounded', function (string $ref, ?string $surface, string $content) {
    Native::test(UiKitScreen::class)
        ->assertElement('pressable', fn (array $node): bool => refNode($ref)($node)
            && $node['layout']['min_height'] == 48
            && $node['layout']['padding'] == [0, 16, 0, 16]
            && $node['style']['border_radius'] == 9999
            && ($node['style']['bg_color'] ?? null) === ($surface === null ? null : theme($surface))
            && isset($node['props']['glass']) === ($surface === null)
            && collect($node['children'])->flatMap(fn (array $child): array => [$child, ...($child['children'] ?? [])])
                ->contains(fn (array $child): bool => $child['type'] === 'text'
                    && $child['props']['font_size'] == 16
                    && $child['props']['font_name'] === 'semibold'
                    && $child['props']['color'] === theme($content)));
})->with([
    'primary' => ['button-primary', 'primary', 'on-primary'],
    'secondary' => ['button-secondary', 'surface-variant', 'on-surface'],
    'glass' => ['button-glass', null, 'on-surface'],
    'destructive' => ['button-destructive', 'destructive', 'on-destructive'],
]);

it('draws a text button with no surface, in the secondary colour', function () {
    Native::test(UiKitScreen::class)
        ->assertElement('pressable', fn (array $node): bool => refNode('button-text')($node)
            && $node['layout'] == ['min_height' => 48, 'justify_content' => 1]
            && ! isset($node['style'])
            && $node['props']['a11y_label'] === 'Rename Camping gear'
            && $node['children'][0]['props']['text'] === 'Rename Camping gear'
            && $node['children'][0]['props']['font_size'] == 16
            && $node['children'][0]['props']['font_name'] === 'semibold'
            && $node['children'][0]['props']['color'] === theme('secondary'));
});

it('puts an 18pt icon in the label’s colour beside the label', function () {
    Native::test(UiKitScreen::class, platform: 'ios')
        ->assertElement('pressable', fn (array $node): bool => refNode('button-primary')($node)
            && $node['props']['a11y_label'] === 'Add a calendar'
            && $node['children'][0]['type'] === 'row'
            && $node['children'][0]['layout']['gap'] == 8
            && $node['children'][0]['children'][0]['props']['name'] === 'plus'
            && $node['children'][0]['children'][0]['props']['size'] == 18
            && $node['children'][0]['children'][0]['props']['color'] === theme('on-primary')
            && $node['children'][0]['children'][1]['props']['text'] === 'Add calendar');
});

it('always gives a destructive button a trash icon', function (string $platform, string $iconName) {
    Native::test(UiKitScreen::class, platform: $platform)
        ->assertElement('pressable', fn (array $node): bool => refNode('button-destructive')($node)
            && $node['children'][0]['children'][0]['props']['name'] === $iconName
            && $node['children'][0]['children'][1]['props']['text'] === 'Delete');
})->with([
    'ios' => ['ios', 'trash'],
    'android' => ['android', 'delete'],
]);

it('gives a disabled or loading button a grey fill, a lighter label and nothing to tap', function () {
    $lighter = '#66'.substr(theme('on-surface'), 1);

    Native::test(UiKitScreen::class)
        ->assertElement('pressable', refNode('button-disabled'))
        ->tap('button-disabled')
        ->tap('button-disabled')
        ->assertSet('saves', 2)
        ->assertMissingElement('pressable', refNode('button-disabled'))
        ->assertElement('column', fn (array $node): bool => refNode('button-disabled')($node)
            && $node['layout']['min_height'] == 48
            && $node['style']['bg_color'] === theme('surface-variant')
            && $node['props']['a11y_label'] === 'Save, unavailable'
            && $node['children'][0]['props']['color'] === $lighter)
        ->assertElement('column', fn (array $node): bool => refNode('button-loading')($node)
            && $node['style']['bg_color'] === theme('surface-variant')
            && $node['props']['a11y_label'] === 'Deleting…, unavailable'
            && array_column($node['children'][0]['children'], 'type') === ['activity_indicator', 'text']);
});

it('keeps a disabled glass or text button on its own surface and only lightens it', function () {
    $lighter = '#66'.substr(theme('on-surface'), 1);

    Native::test(UiKitScreen::class)
        ->assertElement('column', fn (array $node): bool => refNode('button-glass-disabled')($node)
            && isset($node['props']['glass'])
            && ($node['style']['bg_color'] ?? null) !== theme('surface-variant')
            && $node['props']['a11y_label'] === 'Today, unavailable'
            && $node['children'][0]['props']['color'] === $lighter)
        ->assertElement('column', fn (array $node): bool => refNode('button-row-icon-disabled')($node)
            && ! isset($node['style'])
            && $node['props']['a11y_label'] === 'Move up, unavailable'
            && $node['children'][0]['props']['color'] === $lighter);
});

it('makes a filled button with no label a 48pt circle', function (string $platform, string $iconName) {
    Native::test(UiKitScreen::class, platform: $platform)
        ->assertElement('pressable', fn (array $node): bool => refNode('button-circle')($node)
            && $node['layout']['width'] == 48 && $node['layout']['height'] == 48
            && $node['style']['border_radius'] == 9999
            && $node['props']['a11y_label'] === 'More'
            && $node['props']['has_menu'] === true
            && collect($node['children'])->contains(fn (array $child): bool => $child['type'] === 'icon'
                && $child['props']['name'] === $iconName
                && $child['props']['size'] == 22
                && $child['props']['color'] === theme('on-surface')));
})->with([
    'ios' => ['ios', 'ellipsis'],
    'android' => ['android', 'more_horiz'],
]);

it('makes a text button with no label a row icon with a 48pt tap area', function () {
    Native::test(UiKitScreen::class, platform: 'ios')
        ->assertElement('pressable', fn (array $node): bool => refNode('button-row-icon')($node)
            && $node['layout']['width'] == 48 && $node['layout']['height'] == 48
            && ! isset($node['style'])
            && $node['props']['a11y_label'] === 'Remove'
            && $node['children'][0]['props']['name'] === 'xmark'
            && $node['children'][0]['props']['size'] == 18
            && $node['children'][0]['props']['color'] === theme('secondary'));
});

it('runs a button’s tap and follows its link', function () {
    Native::test(UiKitScreen::class)
        ->tap('button-primary')
        ->assertSet('saves', 1)
        ->tap('button-row-icon')
        ->assertSet('saves', 2)
        ->tap('button-glass')
        ->assertNavigatedTo('/login');
});

it('draws the empty state as a card with a coral icon, a heading and one centred line', function () {
    Native::test(UiKitScreen::class, platform: 'ios')
        ->assertElement('column', fn (array $node): bool => refNode('empty-state')($node)
            && $node['style']['bg_color'] === theme('surface')
            && $node['style']['border_radius'] == 18
            && $node['layout']['padding'] == [24, 16, 24, 16]
            && array_column($node['children'], 'type') === ['icon', 'text', 'text']
            && $node['children'][0]['props']['name'] === 'list.bullet'
            && $node['children'][0]['props']['size'] == 28
            && $node['children'][0]['props']['color'] === theme('primary')
            && $node['children'][1]['props']['text'] === 'No lists match “Camping gear”'
            && $node['children'][1]['props']['text_align'] == 1
            && $node['children'][2]['props']['text'] === 'Try a different search.'
            && $node['children'][2]['props']['font_size'] == 14
            && $node['children'][2]['props']['text_align'] == 1);
});
