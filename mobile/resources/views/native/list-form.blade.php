@use('App\Icons\Android')
@use('App\Icons\Ios')

<x-ui.submit-bar ref="{{ $formRef }}-submit" :disabled="$saving || $savedId !== null">{{ $submitLabel }}</x-ui.submit-bar>

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-4 py-6">
        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Groceries"
                autocapitalize="sentences"
                native:model.blur="name"
            />

            <select
                ref="{{ $formRef }}-type"
                label="Type"
                :options="$typeOptions"
                :value="$typeOptions[$typeIndex] ?? ''"
                @change="pickType"
            />

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
    </column>
</scroll-view>
