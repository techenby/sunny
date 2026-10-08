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
            && $node['props']['color'] === theme('primary'))
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
            && $node['layout']['width'] == 40
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
