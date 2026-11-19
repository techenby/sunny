@use('App\Icons\Android')
@use('App\Icons\Ios')

<column class="w-full">
    <pressable
        ref="{{ $ref }}"
        :a11y-label="$headline.($owner ? ', '.$owner['name'] : '').', '.$supporting"
        class="min-h-12 w-full justify-center px-4 py-2.5"
        @navigate($url)
    >
        <row class="w-full items-center gap-4">
            @if ($iconBackground ?? null)
                <column class="h-10 w-10 items-center justify-center rounded-full bg-{{ $iconBackground }}">
                    <icon :ios="$ios" :android="$android" :size="18" class="text-white" />
                </column>
            @else
                <icon :ios="$ios" :android="$android" :size="24" class="text-theme-primary" />
            @endif
            <column class="flex-1 gap-0.5">
                <x-ui.heading size="lg">{{ $headline }}</x-ui.heading>
                <x-ui.text>{{ $supporting }}</x-ui.text>
            </column>
            @if ($owner)
                @include('native.owner-mark', ['owner' => $owner, 'ref' => $ref.'-owner'])
            @endif
            <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
        </row>
    </pressable>
    @unless ($last)
        <divider class="{{ ($iconBackground ?? null) ? 'ml-[72]' : 'ml-14' }} mr-5" />
    @endunless
</column>
