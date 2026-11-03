@use('App\Icons\Android')
@use('App\Icons\Ios')

<column ref="{{ $ref }}" class="w-full gap-2">
    <text class="text-sm font-semibold text-theme-on-surface-variant">{{ $label }}</text>
    @foreach ($options as $index => $option)
        <pressable
            ref="{{ $ref }}-{{ $index }}"
            :a11y-label="$option.($index === $selected ? ', selected' : '')"
            class="min-h-12 w-full justify-center rounded-xl border px-4 py-2 {{ $index === $selected ? 'border-theme-primary bg-theme-primary/10' : 'border-theme-outline bg-theme-surface' }}"
            @tap="{{ $action }}({{ $index }})"
        >
            <row class="w-full items-center gap-4">
                <text class="flex-1 text-base text-theme-on-surface">{{ $option }}</text>
                @if ($index === $selected)
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
