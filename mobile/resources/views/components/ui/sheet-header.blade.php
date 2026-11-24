@props([
    'title',
    'close',
    'ref' => null,
])

@ios
    <column class="absolute top-0 right-0 -bottom-[1000] left-0 h-full w-full bg-theme-background" />
@endios
<row class="w-full items-center px-4 pt-4 pb-2">
    <x-ui.button :ref="$ref" variant="glass" :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\Android::Close" a11y-label="Close" :tap="$close" />
    <text font="semibold" class="flex-1 px-2 text-center text-[17] text-theme-on-surface">{{ $title }}</text>
    <column class="w-12" />
</row>
