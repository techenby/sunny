@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Menu')

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
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
                :ios-leading-icon="Ios::Tag"
                :android-leading-icon="Android::Label"
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
                <text class="text-sm font-semibold text-theme-on-surface-variant">Color</text>
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

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        <column class="w-full gap-2.5">
            @if ($removeLabel ?? null)
                <row class="w-full items-center gap-3">
                    <x-ui.button
                        ref="{{ $formRef }}-submit"
                        class="flex-1"
                        :disabled="$saving"
                        tap="save"
                    >
                        Save calendar
                    </x-ui.button>
                    <x-ui.button
                        ref="{{ $formRef }}-more"
                        variant="secondary"
                        :ios="Ios::Ellipsis"
                        :android="Android::MoreHoriz"
                        a11y-label="More"
                        :menu="[Menu::delete($formRef.'-remove', 'Remove calendar', 'confirmRemove', $removeLabel)]"
                    />
                </row>
            @else
                <x-ui.button
                    ref="{{ $formRef }}-submit"
                    class="w-full"
                    :disabled="$saving"
                    tap="save"
                >
                    Save calendar
                </x-ui.button>
            @endif
            <row class="w-full items-center justify-center gap-1.5">
                <icon :ios="Ios::Wifi" :android="Android::Wifi" :size="14" class="text-theme-on-surface-variant" />
                <x-ui.text size="sm">Needs a connection</x-ui.text>
            </row>
        </column>
    </column>
</scroll-view>
