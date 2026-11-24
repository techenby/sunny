@use('App\Icons\Android')
@use('App\Icons\Ios')

<x-ui.submit-bar
    ref="{{ $formRef }}-submit"
    :disabled="$saving || $disconnected"
>
    {{ $submitLabel }}
</x-ui.submit-bar>

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-4 py-6">
        @if ($failure ?? null)
            <row ref="{{ $formRef }}-failing" class="w-full items-start gap-3 rounded-xl border border-theme-destructive/20 bg-theme-destructive/10 px-4 py-3">
                <icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::Warning" :size="18" class="text-theme-destructive" />
                <column class="flex-1 gap-0.5">
                    <text font="semibold" class="text-sm text-theme-on-surface">This calendar isn’t updating</text>
                    <text class="text-sm text-theme-on-surface-variant">{{ ucfirst($failure) }}. The link may have been reset. Paste the new one and save.</text>
                </column>
            </row>
        @endif

        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Family"
                autocapitalize="words"
                native:model.blur="name"
            />

            <column class="w-full gap-2">
                <outlined-text-input
                    ref="{{ $formRef }}-url"
                    label="Calendar link"
                    placeholder="https://calendar.google.com/…"
                    keyboard="url"
                    autocapitalize="none"
                    :ios-leading-icon="Ios::Link"
                    :android-leading-icon="Android::Link"
                    native:model.blur="url"
                />
                <x-ui.text size="sm">An https iCal address. In Google Calendar, open the calendar’s settings and copy the Secret address in iCal format. Proton and most other apps have a share or subscribe link.</x-ui.text>
            </column>

            <column ref="{{ $formRef }}-color" class="w-full gap-2">
                <x-ui.field-label>Color</x-ui.field-label>
                <row class="w-full items-center justify-between">
                    @foreach ($colorOptions as $index => $option)
                        <pressable
                            ref="{{ $formRef }}-color-{{ $index }}"
                            :a11y-label="$option['label'].($index === $colorIndex ? ', selected' : '')"
                            :class="'h-10 w-10 items-center justify-center rounded-full'.($index === $colorIndex ? ' border-2 border-['.$option['hex'].']' : '')"
                            @tap="chooseColor({{ $index }})"
                        >
                            <column :class="'h-8 w-8 items-center justify-center rounded-full bg-['.$option['hex'].']'">
                                @if ($index === $colorIndex)
                                    <icon :ios="Ios::Checkmark" :android="Android::Check" :size="16" class="text-white" />
                                @endif
                            </column>
                        </pressable>
                    @endforeach
                </row>
            </column>
        </column>

        @if ($disconnected)
            <x-ui.offline ref="{{ $formRef }}-offline" />
        @elseif ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif
    </column>
</scroll-view>
