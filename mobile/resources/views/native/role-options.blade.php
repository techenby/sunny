@use('App\Icons\Android')
@use('App\Icons\Ios')

<column ref="{{ $ref }}" class="w-full gap-2">
    <text class="text-sm font-semibold text-theme-on-surface-variant">Role</text>
    @foreach ($roles as $option)
        <pressable
            ref="{{ $ref }}-{{ $option->value }}"
            :a11y-label="$option->label().($option === $selected ? ', selected' : '')"
            :a11y-hint="$option->description()"
            class="min-h-12 w-full justify-center rounded-xl border px-4 py-2 {{ $option === $selected ? 'border-theme-primary bg-theme-primary/10' : 'border-theme-outline bg-theme-surface' }}"
            @tap="{{ $action }}('{{ $option->value }}')"
        >
            <row class="w-full items-center gap-4">
                <column class="flex-1 gap-0.5">
                    <text class="text-base text-theme-on-surface">{{ $option->label() }}</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ $option->description() }}</text>
                </column>
                @if ($option === $selected)
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
