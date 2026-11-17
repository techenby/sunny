@use('App\Icons\Android')
@use('App\Icons\Ios')

<row ref="{{ $ref }}" class="w-full items-start gap-3 px-4 py-2">
    <column :class="'w-1 min-h-10 self-stretch rounded-full '.($event['declined'] ? 'border border-'.$event['color'] : 'bg-'.$event['color'])" />
    <column class="w-[64] gap-0.5">
        <text font="medium" :class="$event['declined'] ? 'text-sm text-theme-on-surface-variant' : 'text-sm text-theme-on-surface'">{{ $event['time'] }}</text>
        @if ($event['until'])
            <text class="text-xs text-theme-on-surface-variant">{{ $event['until'] }}</text>
        @endif
    </column>
    <column class="flex-1 gap-0.5">
        <text ref="{{ $ref }}-title" font="medium" :class="$event['declined'] ? 'text-base text-theme-on-surface-variant line-through' : 'text-base text-theme-on-surface'">{{ $event['title'] }}</text>
        @if ($event['location'])
            <row class="w-full items-center gap-1">
                <icon :ios="Ios::Mappin" :android="Android::Place" :size="12" class="text-theme-on-surface-variant" />
                <text max-lines="2" class="flex-1 text-sm text-theme-on-surface-variant">{{ $event['location'] }}</text>
            </row>
        @endif
        @if ($event['declined'])
            <x-ui.badge size="sm" class="mt-0.5">Declined</x-ui.badge>
        @endif
    </column>
</row>
