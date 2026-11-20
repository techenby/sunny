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
                'button-ghost',
                'button-filled',
                'button-tint',
                'button-neutral',
                'button-glass',
                'button-outline',
                'button-outline-pill',
                'button-plain',
                'button-icon',
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

it('draws platform buttons for the primary, secondary, destructive and ghost variants', function () {
    Native::test(UiKitScreen::class, platform: 'ios')
        ->assertElement('button', fn (array $node): bool => refNode('button-primary')($node)
            && $node['props']['label'] === 'Save'
            && $node['props']['variant'] === 'primary'
            && $node['props']['size'] === 'lg'
            && $node['props']['font_name'] === 'semibold'
            && $node['props']['leading_icon'] === 'arrow.clockwise'
            && ! isset($node['props']['disabled']))
        ->assertElement('button', fn (array $node): bool => refNode('button-ghost')($node)
            && $node['props']['variant'] === 'ghost'
            && ! isset($node['props']['size'])
            && ! isset($node['props']['font_name']));
});

it('draws each pill variant from the theme', function (string $ref, ?string $surface, string $content, int $radius) {
    Native::test(UiKitScreen::class)
        ->assertElement('pressable', fn (array $node): bool => refNode($ref)($node)
            && $node['layout']['min_height'] == 48
            && $node['layout']['padding'] == [0, 16, 0, 16]
            && ($node['style']['bg_color'] ?? null) === match ($surface) {
                null => null,
                'tint' => '#26E85A48',
                default => theme($surface),
            }
            && isset($node['props']['glass']) === ($surface === null)
            && $node['style']['border_radius'] == $radius
            && collect($node['children'])->flatMap(fn (array $child): array => [$child, ...($child['children'] ?? [])])
                ->contains(fn (array $child): bool => $child['type'] === 'text'
                    && $child['props']['color'] === theme($content)
                    && $child['props']['font_name'] === 'semibold'));
})->with([
    'filled' => ['button-filled', 'primary', 'on-primary', 9999],
    'tint' => ['button-tint', 'tint', 'primary-text', 9999],
    'neutral' => ['button-neutral', 'surface-variant', 'on-surface', 9999],
    'glass with an accent tone' => ['button-glass', null, 'primary-text', 9999],
    'outline' => ['button-outline', 'surface', 'on-surface', 12],
    'outline-pill' => ['button-outline-pill', 'surface', 'on-surface', 9999],
]);

it('gives a button an icon beside its label, sized with the label', function () {
    Native::test(UiKitScreen::class, platform: 'ios')
        ->assertElement('pressable', fn (array $node): bool => refNode('button-filled')($node)
            && $node['props']['a11y_label'] === 'Add a calendar'
            && $node['children'][0]['type'] === 'row'
            && $node['children'][0]['layout']['gap'] == 8
            && $node['children'][0]['children'][0]['props']['name'] === 'plus'
            && $node['children'][0]['children'][0]['props']['size'] == 18
            && $node['children'][0]['children'][1]['props']['text'] === 'Add calendar'
            && $node['children'][0]['children'][1]['props']['font_size'] == 18);
});

it('leaves a plain button without a surface or side padding', function () {
    Native::test(UiKitScreen::class)
        ->assertElement('pressable', fn (array $node): bool => refNode('button-plain')($node)
            && $node['layout'] == ['min_height' => 48, 'justify_content' => 1]
            && ! isset($node['style'])
            && $node['props']['a11y_label'] === 'Delete Camping gear'
            && $node['children'][0]['props']['text'] === 'Delete Camping gear'
            && $node['children'][0]['props']['color'] === theme('destructive'));
});

it('makes a button with no label a 48pt icon button', function (string $platform, string $iconName) {
    Native::test(UiKitScreen::class, platform: $platform)
        ->assertElement('pressable', fn (array $node): bool => refNode('button-icon')($node)
            && $node['layout']['width'] == 48 && $node['layout']['height'] == 48
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

it('runs a button’s tap and follows its link', function () {
    Native::test(UiKitScreen::class)
        ->tap('button-primary')
        ->assertSet('saves', 1)
        ->tap('button-filled')
        ->assertSet('saves', 2)
        ->assertElement('button', fn (array $node): bool => refNode('button-primary')($node) && $node['props']['disabled'] === true)
        ->tap('button-ghost')
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
