@props([
    'heading',
    'text',
    'ios' => null,
    'android' => null,
    'ref' => null,
])

<column :ref="$ref" class="{{ \App\Ui\Card::classes() }} w-full items-center gap-2 px-4 py-6">
    <icon :ios="$ios" :android="$android" :size="28" class="text-theme-primary" />
    <x-ui.heading class="text-center">{{ $heading }}</x-ui.heading>
    <x-ui.text size="sm" class="text-center">{{ $text }}</x-ui.text>
</column>
