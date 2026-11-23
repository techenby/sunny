@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Expects $refPrefix and $queuedChange; the including screen supplies
     confirmDiscardQueuedChange via the ShowsQueuedChange trait. --}}

@if (($change = $queuedChange) && $change['error'])
    <column ref="{{ $refPrefix }}-sync-error" class="w-full gap-2 rounded-xl bg-theme-surface px-4 py-3">
        <row class="w-full items-center gap-2">
            <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::ErrorOutline" :size="16" class="text-theme-destructive" />
            <text font="semibold" class="flex-1 text-base text-theme-on-surface">Not saved to Sunny</text>
        </row>
        <text class="text-sm text-theme-on-surface-variant">{{ $change['error'] }} Edit to try again, or discard the change.</text>
        <x-ui.button
            ref="{{ $refPrefix }}-sync-discard"
            variant="text"
            class="self-start"
            tap="confirmDiscardQueuedChange"
        >Discard change</x-ui.button>
    </column>
@endif
