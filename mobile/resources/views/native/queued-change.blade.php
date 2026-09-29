@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Expects $refPrefix and $queuedChange; the including screen supplies
     confirmDiscardQueuedChange via the ShowsQueuedChange trait. --}}

@if ($change = $queuedChange)
    @if ($change['error'])
        <column ref="{{ $refPrefix }}-sync-error" class="w-full gap-2 rounded-xl bg-theme-surface px-4 py-3">
            <row class="w-full items-center gap-2">
                <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::ErrorOutline" :size="16" class="text-theme-destructive" />
                <text font="semibold" class="flex-1 text-base text-theme-on-surface">Not saved to Sunny</text>
            </row>
            <text class="text-sm text-theme-on-surface-variant">{{ $change['error'] }} Edit to try again, or discard the change.</text>
            <pressable ref="{{ $refPrefix }}-sync-discard" class="h-10 justify-center self-start" @tap="confirmDiscardQueuedChange">
                <text class="text-sm font-semibold text-theme-destructive">Discard change</text>
            </pressable>
        </column>
    @else
        <row ref="{{ $refPrefix }}-sync-pending" class="w-full items-center gap-2">
            <icon :ios="Ios::IcloudAndArrowUp" :android="Android::CloudUpload" :size="14" class="text-theme-on-surface-variant" />
            <text class="flex-1 text-sm text-theme-on-surface-variant">Saved on this phone · syncing with Sunny</text>
        </row>
    @endif
@endif
