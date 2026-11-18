@props([
    'ref' => null,
    'ios' => null,
    'android' => null,
    'title' => '',
    'message' => '',
])

<row :ref="$ref" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
    <icon :ios="$ios" :android="$android" :size="20" class="text-theme-primary-text" />
    <column class="flex-1 gap-0.5">
        <text font="semibold" class="text-base text-theme-primary-text">{{ $title }}</text>
        <text class="text-base text-theme-on-surface">{{ $message }}</text>
    </column>
</row>
