@use('App\Icons\Android')
@use('App\Icons\Ios')

@php
    $tile ??= false;
    $photo ??= null;
    $iconBackground ??= null;
    $iconColor ??= 'white';
@endphp

<column class="w-full">
    <pressable
        ref="{{ $ref }}"
        :a11y-label="implode(', ', array_filter([$headline, $owner['name'] ?? null, $supporting]))"
        class="min-h-12 w-full justify-center px-4 py-2.5"
        @navigate($url)
    >
        <row class="w-full items-center gap-4">
            @if ($tile && $photo)
                <image :src="$photo" :alt="'Photo of '.$headline" class="h-10 w-10 rounded-lg object-cover" />
            @elseif ($tile)
                <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                    <icon :ios="$ios" :android="$android" :size="22" class="text-theme-primary" />
                </column>
            @elseif ($iconBackground)
                <column class="h-10 w-10 items-center justify-center rounded-full bg-{{ $iconBackground }}">
                    <icon :ios="$ios" :android="$android" :size="18" class="text-{{ $iconColor }}" />
                </column>
            @else
                <icon :ios="$ios" :android="$android" :size="24" class="text-theme-primary" />
            @endif
            <column class="flex-1 gap-0.5">
                <x-ui.heading size="lg">{{ $headline }}</x-ui.heading>
                @if ($supporting)
                    <x-ui.text>{{ $supporting }}</x-ui.text>
                @endif
            </column>
            @if ($owner)
                @include('native.owner-mark', ['owner' => $owner, 'ref' => $ref.'-owner'])
            @endif
            <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
        </row>
    </pressable>
    @unless ($last)
        <divider class="{{ $tile || $iconBackground ? 'ml-[72]' : 'ml-14' }}" />
    @endunless
</column>
