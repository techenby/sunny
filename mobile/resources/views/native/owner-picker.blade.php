@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($choices = [['id' => 0, 'name' => 'Household', 'initials' => null, 'supporting' => 'Everyone in the team'], ...$members])

<column ref="{{ $ref }}" class="w-full gap-2">
    <x-ui.field-label>{{ $label }}</x-ui.field-label>
    @foreach ($choices as $choice)
        @php($chosen = $choice['id'] === ($selected ?? 0))
        <pressable
            ref="{{ $ref }}-{{ $choice['id'] }}"
            :a11y-label="$choice['name'].($chosen ? ', selected' : '')"
            class="min-h-12 w-full justify-center rounded-xl border py-2 pr-4 pl-2 {{ $chosen ? 'border-theme-primary bg-theme-primary/10' : 'border-theme-outline bg-theme-surface' }}"
            @tap="chooseOwner({{ $choice['id'] }})"
        >
            <row class="w-full items-center gap-3">
                @include('native.owner-mark', ['owner' => $choice, 'size' => 'md'])
                <column class="flex-1">
                    <text class="text-base text-theme-on-surface">{{ $choice['name'] }}</text>
                    @isset($choice['supporting'])
                        <text class="text-xs text-theme-on-surface-variant">{{ $choice['supporting'] }}</text>
                    @endisset
                </column>
                @if ($chosen)
                    <column class="h-6 w-6 items-center justify-center rounded-full bg-theme-primary">
                        <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
                    </column>
                @else
                    <column class="h-6 w-6 rounded-full border-2 border-theme-outline" />
                @endif
            </row>
        </pressable>
    @endforeach
</column>
