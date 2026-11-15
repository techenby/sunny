@props(['href', 'key' => null])

<flux:table.row
    :$key
    :attributes="$attributes->class('group/row cursor-pointer hover:bg-zinc-50 has-focus-visible:bg-zinc-50 dark:hover:bg-white/5 dark:has-focus-visible:bg-white/5 [&>td]:border-zinc-100! [&>td]:py-2.5 dark:[&>td]:border-white/10!')"
    x-data
    x-on:click="if (! $event.target.closest('a, button, input, dialog, [popover]')) { $event.metaKey || $event.ctrlKey ? window.open('{{ $href }}', '_blank') : Livewire.navigate('{{ $href }}') }"
>
    {{ $slot }}
</flux:table.row>
