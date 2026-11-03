@props([
    'thing',
    'ref' => null,
    'class' => '',
])

<column :ref="$ref" fill center class="gap-2 bg-theme-background px-6 {{ $class }}">
    <icon :ios="\App\Icons\Ios::Magnifyingglass" :android="\App\Icons\Android::SearchOff" :size="32" class="text-theme-on-surface-variant" />
    <x-ui.heading size="lg" class="text-center">{{ ucfirst($thing) }} not found</x-ui.heading>
    <x-ui.text class="text-center">It may have been deleted, or it’s in another team.</x-ui.text>
</column>
