@props(['level' => 2, 'group'])

@php
    $size = match ($level) {
        2 => 'xl',
        3 => 'lg',
        4 => null,
        5 => null,
    };
    $margin = match ($level) {
        2 => 'mt-8 mb-2',
        3 => 'mt-6 mb-2',
        4 => 'mt-4 mb-2',
        5 => 'mt-4 mb-2',
    };
@endphp

<div class="max-w-prose {{ $margin }}">
    <flux:heading :id="$group->slug" :$level :$size>{{ $group->name }}</flux:heading>
    <flux:subheading>{{ $group->summary }}</flux:subheading>
</div>
