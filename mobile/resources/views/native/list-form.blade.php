@use('App\Icons\Android')
@use('App\Icons\Ios')

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Groceries"
                autocapitalize="sentences"
                :ios-leading-icon="Ios::Tag"
                :android-leading-icon="Android::Label"
                native:model.blur="name"
            />

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Type</text>
                <button-group
                    ref="{{ $formRef }}-type"
                    :options="$typeOptions"
                    a11y-label="List type"
                    native:model="typeIndex"
                />
            </column>
        </column>

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-sm text-theme-destructive">
                {{ $error }}
            </text>
        @endif

        <button
            ref="{{ $formRef }}-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            :disabled="$saving || $savedId !== null"
            @tap="save"
        >
            {{ $submitLabel }}
        </button>
    </column>
</scroll-view>
