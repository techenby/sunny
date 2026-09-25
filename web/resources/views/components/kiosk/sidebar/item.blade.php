@props(['icon'])

@php
    $classes = Flux::classes()
        ->add('flex flex-col items-center justify-center p-2 h-20')
        ->add('data-current:text-(--color-accent-content) hover:data-current:text-(--color-accent-content)')
        ->add('data-current:bg-white dark:data-current:bg-white/[7%]')
        ->add('last:border-b last:border-zinc-200 last:dark:border-zinc-700')
        ;
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <flux:icon :$icon />
    <flux:text>{{ $slot }}</flux:text>
</a>
