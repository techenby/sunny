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

            @include('native.option-list', [
                'ref' => $formRef.'-type',
                'label' => 'Type',
                'options' => $typeOptions,
                'selected' => $typeIndex,
                'action' => 'chooseType',
            ])

            @if ($ownerOptions)
                @include('native.owner-picker', [
                    'ref' => $formRef.'-owner',
                    'label' => 'Belongs to',
                    'members' => $ownerOptions,
                    'selected' => $userId,
                ])
            @endif
        </column>

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-sm text-theme-destructive">
                {{ $error }}
            </text>
        @endif

        <x-ui.button
            ref="{{ $formRef }}-submit"
            class="w-full"
            :disabled="$saving || $savedId !== null"
            tap="save"
        >
            {{ $submitLabel }}
        </x-ui.button>
    </column>
</scroll-view>
