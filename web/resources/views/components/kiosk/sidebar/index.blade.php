@blaze(fold: true)

@php
    $classes = Flux::classes('[grid-area:sidebar]')
        ->add('z-1 flex flex-col [:where(&)]:w-32')
        ->add('bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700')
        ->add('divide-y divide-zinc-200 dark:divide-zinc-700')
        // ->add('data-flux-sidebar-collapsed-desktop:w-14 data-flux-sidebar-collapsed-desktop:px-2')
        // ->add('data-flux-sidebar-collapsed-desktop:cursor-e-resize rtl:data-flux-sidebar-collapsed-desktop:cursor-w-resize')
        ;
@endphp

<div
    {{ $attributes->class($classes) }}
>
    {{ $slot }}
</div>
