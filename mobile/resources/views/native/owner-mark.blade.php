@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($large = ($size ?? 'sm') === 'md')

@if ($owner['initials'] === null)
    <column :ref="$ref ?? null" :a11y-label="$owner['name']" class="{{ $large ? 'h-8 w-8' : 'h-7 w-7' }} items-center justify-center rounded-full bg-theme-on-surface-variant/15">
        <icon :ios="Ios::House" :android="Android::Home" :size="$large ? 18 : 16" class="text-theme-on-surface-variant" />
    </column>
@else
    <column :ref="$ref ?? null" :a11y-label="$owner['name']" class="{{ $large ? 'h-8 w-8' : 'h-7 w-7' }} items-center justify-center rounded-full bg-theme-primary/15">
        <text font="bold" class="{{ $large ? 'text-sm' : 'text-xs' }} text-theme-primary-text">{{ $owner['initials'] }}</text>
    </column>
@endif
