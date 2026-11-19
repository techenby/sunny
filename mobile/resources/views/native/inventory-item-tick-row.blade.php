@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Inventory')

<pressable
    ref="{{ $ref }}"
    :a11y-label="$item['name'].($selected ? ', selected' : '')"
    class="min-h-12 w-full justify-center px-4 py-2 {{ $selected ? 'bg-theme-primary/5 '.$corners : '' }}"
    @tap="toggleSelected({{ $item['id'] }})"
>
    <row class="w-full items-center gap-4">
        @if ($selected)
            <column class="h-6 w-6 items-center justify-center rounded-full bg-theme-primary">
                <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
            </column>
        @else
            <column class="h-6 w-6 rounded-full border-2 border-theme-outline" />
        @endif
        <x-ui.avatar
            :src="$item['thumb_path'] ?? $item['photo_url'] ?? null"
            :alt="'Photo of '.$item['name']"
            :color="$item['type']->color()"
            :ios="$item['type']->iosIcon()"
            :android="$item['type']->androidIcon()"
        />
        <column class="flex-1 gap-0.5">
            <x-ui.heading size="lg">{{ $item['name'] }}</x-ui.heading>
            <x-ui.text>{{ Inventory::summary($item) }}</x-ui.text>
        </column>
    </row>
</pressable>
