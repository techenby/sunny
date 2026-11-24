@props([
    'tap' => 'save',
    'ios' => null,
    'android' => null,
    'disabled' => false,
    'loading' => false,
    'ref' => null,
    'a11yHint' => null,
])

<native:bottom-bar>
    <row class="w-full items-center gap-3 px-4 pb-2">
        <x-ui.button :ref="$ref" class="flex-1" :ios="$ios" :android="$android" :disabled="$disabled" :loading="$loading" :a11y-hint="$a11yHint" :tap="$tap">{{ $slot }}</x-ui.button>
    </row>
</native:bottom-bar>
