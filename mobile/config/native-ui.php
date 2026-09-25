<?php

/**
 * Native UI — Theme Tokens
 *
 * Published via `php artisan vendor:publish --tag=native-ui-config`.
 * Edit to customize your app's visual identity in one place.
 *
 * For dynamic per-tenant theming, use Nativephp\NativeUi\Theme::merge([...])
 * from a service provider. Runtime merges deep-merge on top of these values.
 *
 * Decision log: /docs/NATIVE-UI-REWRITE-PLAN.md (D — theme layer)
 */

return [

    /*
    |---------------------------------------------------------------------------
    | Theme
    |---------------------------------------------------------------------------
    |
    | 17 color tokens, 4 radii, 4 font sizes, font family.
    |
    | "on-X" means "color of content placed ON a surface of color X"
    |   — i.e., text/icons on that background.
    |
    | Color tokens accept:
    |   - CSS hex: '#B91C1C', '#F00', or with alpha '#8B5CF680' (#RRGGBBAA)
    |   - Tailwind palette names: 'red-300', 'orange-800'
    |   - Opacity modifiers on either: 'red-300/20', '#8B5CF6/50'
    |
    | Dark mode is auto-derived from `light` when `dark` is not set. To opt
    | into explicit dark tokens, fill out the `dark` block.
    |
    | The default pairs meet WCAG AA (4.5:1) — if you customize, keep each
    | `on-*` color at 4.5:1 contrast against its background token.
    |
    */

    'theme' => [

        'light' => [
            // Primary brand color — used for filled buttons, active states, key accents.
            'primary' => '#E85A48',
            'on-primary' => '#FCF7F6',

            // Secondary / muted action color.
            'secondary' => '#463E3B',
            'on-secondary' => '#FCF7F6',

            // Surface = cards, sheets, dialogs. Background = page root.
            'surface' => '#FEF9F7',
            'on-surface' => '#0B0808',
            'background' => '#FAF1EF',
            'on-background' => '#0B0808',

            // iOS systemGroupedBackground — what sectioned lists paint behind
            // their rows. Forms use it on iOS so they sit alongside those lists.
            'grouped-background' => '#FAF1EF',

            // Surface variant = filled text fields, muted tonal surfaces.
            // on-surface-variant = muted label/hint text on those surfaces.
            'surface-variant' => '#F0E5E2',
            'on-surface-variant' => '#5D5350',

            // Outline = neutral borders (text fields, dividers, cards).
            'outline' => '#DDD1CE',

            // Destructive actions — maps to `variant="destructive"` on components.
            'destructive' => '#B91C1C',
            'on-destructive' => '#FCF7F6',

            // Tertiary accent — for highlights, badges, emphasis not covered by primary.
            'accent' => '#E85A48',
            'on-accent' => '#FCF7F6',
        ],

        'dark' => [
            // Leave empty or partial to auto-derive from `light` (luminance inversion).
            // Specify any token here to override the derived value.
            'primary' => '#F87966',
            'on-primary' => '#150A08',

            'secondary' => '#DDD1CE',
            'on-secondary' => '#150A08',

            'surface' => '#1A1413',
            'on-surface' => '#FEF9F7',
            'background' => '#0C0807',
            'on-background' => '#FEF9F7',

            'grouped-background' => '#0C0807',

            'surface-variant' => '#2C2422',
            'on-surface-variant' => '#A89B98',

            'outline' => '#463E3B',

            'destructive' => '#F87171',
            'on-destructive' => '#150A08',

            'accent' => '#F87966',
            'on-accent' => '#150A08',
        ],

        // Corner radii (points / dp).
        'radius-sm' => 5,
        'radius-md' => 8,
        'radius-lg' => 12,
        'radius-full' => 9999,

        // Font size scale (points / sp).
        'font-sm' => 14,
        'font-md' => 16,
        'font-lg' => 20,
        'font-xl' => 24,
    ],

    'fonts' => [
        'default' => 'Nunito-Regular',
        'medium' => 'Nunito-Medium',
        'semibold' => 'Nunito-SemiBold',
        'accent' => 'Nunito-Bold',
        'mono' => 'UbuntuMono-Regular',
        'mono-bold' => 'UbuntuMono-Bold',
    ],

];
