@use('App\Icons\Android')
@use('App\Icons\Ios')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     $typeOptions, $parentOptions, and optionally $subtitle; the remaining
     state comes from the including component's ManagesInventoryItemForm
     properties. Computed values have to be passed in because an @include is
     not bound to $this. --}}

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        @isset($subtitle)
            <text ref="{{ $formRef }}-subtitle" class="text-base text-theme-on-surface-variant">
                {{ $subtitle }}
            </text>
        @endisset

        <column class="w-full gap-4">
            <outlined-text-input
                ref="{{ $formRef }}-name"
                label="Name"
                placeholder="Camping tent"
                autocapitalize="sentences"
                :ios-leading-icon="Ios::Tag"
                :android-leading-icon="Android::Label"
                native:model.blur="name"
            />

            @include('native.photo-field', [
                'photoPath' => $photoPath,
                'refPrefix' => $formRef,
                'alt' => 'The photo chosen for this item',
            ])

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Type</text>
                <button-group
                    ref="{{ $formRef }}-type"
                    :options="$typeOptions"
                    a11y-label="Item type"
                    native:model="typeIndex"
                />
            </column>

            <select
                ref="{{ $formRef }}-parent"
                label="Inside"
                :options="$parentOptions"
                native:model="parentName"
            />
        </column>

        <column class="w-full gap-2">
            <text class="text-sm font-semibold text-theme-on-surface-variant">Metadata</text>

            @foreach ($metadata as $index => $pair)
                <row class="w-full items-center gap-2">
                    <outlined-text-input
                        ref="metadata-key-{{ $index }}"
                        placeholder="Key"
                        class="flex-1"
                        a11y-label="Metadata key {{ $index + 1 }}"
                        :value="$pair['key']"
                        sync-mode="blur"
                        @change="setMetadataKey({{ $index }})"
                    />
                    <outlined-text-input
                        ref="metadata-value-{{ $index }}"
                        placeholder="Value"
                        class="flex-1"
                        a11y-label="Metadata value {{ $index + 1 }}"
                        :value="$pair['value']"
                        sync-mode="blur"
                        @change="setMetadataValue({{ $index }})"
                    />
                    <pressable
                        ref="metadata-remove-{{ $index }}"
                        class="h-12 w-12 items-center justify-center"
                        a11y-label="Remove metadata field {{ $index + 1 }}"
                        @tap="removeMetadata({{ $index }})"
                    >
                        <native:icon
                            :ios="Ios::Xmark"
                            :android="Android::Close"
                            :size="20"
                            class="text-theme-on-surface-variant"
                        />
                    </pressable>
                </row>
            @endforeach

            <pressable
                ref="metadata-add"
                class="h-12 items-start justify-center self-start px-2"
                @tap="addMetadata"
            >
                <text class="text-sm font-semibold text-theme-primary">Add field</text>
            </pressable>
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
            @tap="save"
        >
            {{ $submitLabel }}
        </button>
    </column>
</scroll-view>
