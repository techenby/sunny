@props([
    'ref' => null,
    'headline' => null,
    'plain' => false,
    'supporting' => null,
    'strong' => false,
    'badge' => null,
    'badgeColor' => null,
    'error' => null,
    'errorRef' => null,
    'maxLines' => null,
    'ios' => null,
    'android' => null,
    'monogram' => null,
    'iconBackground' => null,
    'iconColor' => 'white',
    'tile' => false,
    'src' => null,
    'alt' => null,
    'avatarColor' => null,
    'number' => null,
    'photo' => null,
    'photoRef' => null,
    'tone' => null,
    'trailing' => null,
    'trailingText' => null,
    'toggle' => null,
    'owner' => null,
    'menu' => null,
    'menuRef' => null,
    'menuLabel' => null,
    'action' => null,
    'tap' => null,
    'url' => null,
    'from' => false,
    'pressRef' => null,
    'hint' => null,
    'a11yLabel' => null,
    'disabled' => false,
    'last' => false,
    'top' => false,
    'highlighted' => false,
])

@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Row')

@php
$leading = match (true) {
    $photo !== null => 'photo',
    $number !== null => 'number',
    $monogram || $avatarColor || $tile || $iconBackground || $ios || $android => 'box',
    default => 'none',
};

$color = $tone === 'destructive' ? 'text-theme-destructive' : 'text-theme-on-surface';
$glyphColor = match ($tone) {
    'add' => 'text-theme-primary',
    'destructive' => 'text-theme-destructive',
    default => 'text-theme-on-surface-variant',
};
$trailingIcon = match ($trailing) {
    'chevron' => [Ios::ChevronRight, Android::ChevronRight, 14, 'text-theme-on-surface-variant'],
    'external' => [Ios::ArrowUpRight, Android::OpenInNew, 16, 'text-theme-on-surface-variant'],
    'refresh' => [Ios::ArrowClockwise, Android::Refresh, 16, 'text-theme-primary-text'],
    'check' => [Ios::Checkmark, Android::Check, 18, 'text-theme-primary-text'],
    default => null,
};

$a11yLabel ??= implode(', ', array_filter([$headline, $owner['name'] ?? null, $badge, $supporting, $trailingText, $trailing === 'check' ? 'selected' : null]));
$button = $menu !== null || $action !== null;
$bodyClass = $button
    ? 'min-h-12 flex-1 justify-center py-2 pl-4'
    : 'min-h-12 w-full justify-center px-4 py-2'.($disabled ? ' opacity-50' : '').($highlighted ? ' bg-theme-primary/10' : '');
$bodyRef = $button ? $pressRef : $ref;
$pressable = ! $toggle && ! $disabled && ($tap || $url);
@endphp

@if ($button)
    <row :ref="$ref" class="w-full items-center pr-1 {{ $highlighted ? 'bg-theme-primary/10' : '' }}">
@endif
@if ($pressable && $url && $from !== false)
    <pressable :ref="$bodyRef" :a11y-label="$a11yLabel" :a11y-hint="$hint" class="{{ $bodyClass }}" @navigate($url, ['from' => $from])>
@elseif ($pressable && $url)
    <pressable :ref="$bodyRef" :a11y-label="$a11yLabel" :a11y-hint="$hint" class="{{ $bodyClass }}" @navigate($url)>
@elseif ($pressable)
    <pressable :ref="$bodyRef" :a11y-label="$a11yLabel" :a11y-hint="$hint" class="{{ $bodyClass }}" @tap="{{ $tap }}">
@elseif ($toggle)
    <column :ref="$bodyRef" class="{{ $bodyClass }}">
@else
    <column :ref="$bodyRef" :a11y-label="$a11yLabel" class="{{ $bodyClass }}">
@endif
    <row class="w-full {{ $top ? 'items-start' : 'items-center' }} {{ Row::gap($leading) }}">
        @if ($photo !== null)
            <image :ref="$photoRef" :src="$photo" :alt="$alt" class="h-16 w-16 rounded-xl object-cover" />
        @elseif ($number !== null)
            <text class="w-6 text-center text-sm text-theme-on-surface-variant">{{ $number }}</text>
        @elseif ($monogram)
            <column class="h-10 w-10 items-center justify-center rounded-full bg-theme-primary">
                <text font="medium" class="text-base text-theme-on-primary">{{ $monogram }}</text>
            </column>
        @elseif ($avatarColor)
            <x-ui.avatar :src="$src" :alt="$alt" :color="$avatarColor" :ios="$ios" :android="$android" />
        @elseif ($tile && $src)
            <image :src="$src" :alt="$alt" class="h-10 w-10 rounded-lg object-cover" />
        @elseif ($tile)
            <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                <icon :ios="$ios" :android="$android" :size="22" class="text-theme-primary" />
            </column>
        @elseif ($iconBackground)
            <column class="h-10 w-10 items-center justify-center rounded-full bg-{{ $iconBackground }}">
                <icon :ios="$ios" :android="$android" :size="18" class="text-{{ $iconColor }}" />
            </column>
        @elseif ($ios || $android)
            <column class="h-10 w-10 items-center justify-center">
                <icon :ios="$ios" :android="$android" :size="22" class="{{ $glyphColor }}" />
            </column>
        @endif
        @if ($headline !== null || $supporting !== null || $badge !== null || $error !== null)
            <column class="flex-1 gap-0.5">
                @if ($headline !== null)
                    @if ($plain)
                        <text :max-lines="$maxLines" class="text-base {{ $color }}">{{ $headline }}</text>
                    @else
                        <text font="medium" :max-lines="$maxLines" class="text-lg {{ $color }}">{{ $headline }}</text>
                    @endif
                @endif
                @if ($badge !== null)
                    <x-ui.badge size="sm" class="self-start" :color="$badgeColor">{{ $badge }}</x-ui.badge>
                @endif
                @if ($supporting !== null)
                    @if ($strong)
                        <text font="semibold" class="text-base text-theme-on-surface-variant">{{ $supporting }}</text>
                    @else
                        <x-ui.text>{{ $supporting }}</x-ui.text>
                    @endif
                @endif
                @if ($error !== null)
                    <text :ref="$errorRef" class="text-sm text-theme-destructive">{{ $error }}</text>
                @endif
            </column>
        @else
            <column class="flex-1" />
        @endif
        @if ($owner)
            @include('native.owner-mark', ['owner' => $owner, 'ref' => $ref.'-owner'])
        @endif
        @if ($toggle)
            <toggle :ref="$ref.'-toggle'" :value="$toggle['value']" :a11y-label="$headline" @change="{{ $toggle['change'] }}" />
        @elseif ($trailingText)
            <text :ref="$ref.'-trailing'" font="semibold" class="text-base {{ $disabled ? 'text-theme-on-surface-variant' : 'text-theme-secondary' }}">{{ $trailingText }}</text>
        @elseif ($trailingIcon)
            <icon :ref="$ref.'-trailing'" :ios="$trailingIcon[0]" :android="$trailingIcon[1]" :size="$trailingIcon[2]" class="{{ $top ? 'self-center ' : '' }}{{ $trailingIcon[3] }}" />
        @endif
    </row>
@if ($pressable)
    </pressable>
@else
    </column>
@endif
@if ($menu !== null)
    <x-ui.button
        :ref="$menuRef ?? $ref.'-more'"
        variant="text"
        :ios="Ios::Ellipsis"
        :android="Android::MoreHoriz"
        :a11y-label="$menuLabel ?? 'More for '.$headline"
        :menu="$menu"
    />
@elseif ($action !== null)
    <x-ui.button
        :ref="$action['ref']"
        variant="text"
        :ios="$action['ios']"
        :android="$action['android']"
        :a11y-label="$action['label']"
        :tap="$action['tap']"
    />
@endif
@if ($button)
    </row>
@endif
@unless ($last)
    <divider class="{{ Row::inset($leading) }}" />
@endunless
