@props(['completed' => false])

<button
    type="button"
    role="checkbox"
    aria-checked="{{ $completed ? 'true' : 'false' }}"
    {{ $attributes->class('-mx-2 flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50') }}
>
    @if ($completed)
        <flux:icon name="check-circle" variant="solid" class="size-5 shrink-0 text-(--color-accent)" />
    @else
        <span class="size-5 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
    @endif

    <span @class([
        'flex-1 text-sm',
        'text-zinc-400 line-through dark:text-zinc-500' => $completed,
        'text-zinc-800 dark:text-zinc-100' => ! $completed,
    ])>
        {{ $slot }}
    </span>
</button>
