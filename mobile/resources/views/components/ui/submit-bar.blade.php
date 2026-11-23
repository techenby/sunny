@props([
    'tap' => 'save',
    'ios' => null,
    'android' => null,
    'disabled' => false,
    'loading' => false,
    'ref' => null,
    'a11yHint' => null,
    'moreRef' => null,
    'moreMenu' => null,
])

<native:bottom-bar>
    <row class="w-full items-center gap-3 px-4 pb-2">
        <x-ui.button :ref="$ref" class="flex-1" :ios="$ios" :android="$android" :disabled="$disabled" :loading="$loading" :a11y-hint="$a11yHint" :tap="$tap">{{ $slot }}</x-ui.button>
        @if ($moreMenu)
            <x-ui.button :ref="$moreRef" variant="glass" :ios="\App\Icons\Ios::Ellipsis" :android="\App\Icons\Android::MoreHoriz" a11y-label="More" :menu="$moreMenu" />
        @endif
    </row>
</native:bottom-bar>
