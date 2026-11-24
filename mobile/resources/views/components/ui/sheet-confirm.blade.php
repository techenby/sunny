@props([
    'tap',
    'ios' => null,
    'android' => null,
    'loading' => false,
    'help' => null,
    'ref' => null,
])

<column class="w-full gap-2 px-4 pt-6 pb-6 ios:pb-0">
    <x-ui.button :ref="$ref" class="w-full" :ios="$ios" :android="$android" :loading="$loading" :tap="$tap">{{ $slot }}</x-ui.button>
    @if ($help)
        <x-ui.field-help class="text-center">{{ $help }}</x-ui.field-help>
    @endif
</column>
