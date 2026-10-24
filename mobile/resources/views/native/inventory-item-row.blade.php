@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Inventory')

{{-- One inventory entry in a card. Expects $item, $ref and $from, and
     optionally $supporting to replace the "what's inside" summary. --}}

<pressable
    ref="{{ $ref }}"
    :a11y-label="$item['name']"
    class="min-h-12 w-full justify-center px-4 py-2"
    @navigate('/inventory/'.$item['id'], ['from' => $from])
>
    <row class="w-full items-center gap-4">
        <x-ui.avatar
            :src="$item['thumb_path'] ?? $item['photo_url'] ?? null"
            :alt="'Photo of '.$item['name']"
            :color="$item['type']->color()"
            :ios="$item['type']->iosIcon()"
            :android="$item['type']->androidIcon()"
        />
        <column class="flex-1 gap-0.5">
            <x-ui.heading size="lg">{{ $item['name'] }}</x-ui.heading>
            <x-ui.text>{{ $supporting ?? Inventory::summary($item) }}</x-ui.text>
        </column>
        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
    </row>
</pressable>
