@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     $typeChoices, $selectedType, $selectedParent, $browsedParent,
     $parentPickerRows and $parentTrail; the remaining state comes from the
     including component's ManagesInventoryItemForm properties. Computed
     values have to be passed in because an @include is not bound to $this. --}}

<x-ui.submit-bar
    ref="{{ $formRef }}-submit"
    :disabled="$saving || $savedId !== null"
>
    {{ $submitLabel }}
</x-ui.submit-bar>

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-4 py-6">
        <outlined-text-input
            ref="{{ $formRef }}-name"
            label="Name"
            placeholder="Camping tent"
            autocapitalize="sentences"
            native:model.blur="name"
        />

        @include('native.parent-field', [
            'refPrefix' => $formRef,
            'selectedParent' => $selectedParent,
            'noParentLabel' => $selectedType === ItemType::Location ? 'Home' : null,
        ])

        <column class="w-full gap-1">
            <x-ui.field-label>What kind is it?</x-ui.field-label>
            <column class="w-full gap-2">
            @foreach ($typeChoices as $type)
                <pressable
                    ref="{{ $formRef }}-type-{{ $type->value }}"
                    :a11y-label="$type->label().($type === $selectedType ? ', selected' : '')"
                    :a11y-hint="$type->description()"
                    class="min-h-12 w-full justify-center rounded-xl border px-4 py-2 {{ $type === $selectedType ? 'border-theme-primary bg-theme-primary/10' : 'border-theme-outline bg-theme-surface' }}"
                    @tap="chooseType('{{ $type->value }}')"
                >
                    <row class="w-full items-start gap-4">
                        <x-ui.avatar :color="$type->color()" :ios="$type->iosIcon()" :android="$type->androidIcon()" />
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">{{ $type->label() }}</x-ui.heading>
                            <x-ui.text>{{ $type->description() }}</x-ui.text>
                        </column>
                        @if ($type === $selectedType)
                            <column class="self-center h-6 w-6 items-center justify-center rounded-full bg-theme-primary">
                                <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
                            </column>
                        @else
                            <column class="self-center h-6 w-6 rounded-full border-2 border-theme-outline" />
                        @endif
                    </row>
                </pressable>
            @endforeach
            </column>
            <x-ui.field-help>Not sure? Leave it as a thing.</x-ui.field-help>
        </column>

        @include('native.item-photos-field', ['photos' => $photos, 'refPrefix' => $formRef])

        <column class="w-full gap-1">
            <x-ui.field-label>Extra details (optional)</x-ui.field-label>

            <column class="{{ Card::classes() }} w-full">
                @foreach ($metadata as $index => $pair)
                    <row class="w-full items-center gap-2 py-2 pr-1 pl-3">
                        <outlined-text-input
                            ref="metadata-key-{{ $index }}"
                            placeholder="e.g. Brand"
                            class="flex-1"
                            a11y-label="Detail {{ $index + 1 }} name"
                            :value="$pair['key']"
                            sync-mode="blur"
                            @change="setMetadataKey({{ $index }})"
                        />
                        <outlined-text-input
                            ref="metadata-value-{{ $index }}"
                            placeholder="e.g. REI"
                            class="flex-1"
                            a11y-label="Detail {{ $index + 1 }}"
                            :value="$pair['value']"
                            sync-mode="blur"
                            @change="setMetadataValue({{ $index }})"
                        />
                        <x-ui.button
                            ref="metadata-remove-{{ $index }}"
                            variant="text"
                            :ios="Ios::Xmark"
                            :android="Android::Close"
                            a11y-label="Remove detail {{ $index + 1 }}"
                            tap="removeMetadata({{ $index }})"
                        />
                    </row>
                    <divider class="ml-3" />
                @endforeach

                <x-ui.button
                    ref="metadata-add"
                    variant="text"
                    class="w-full px-4"
                    :ios="Ios::Plus"
                    :android="Android::Add"
                    a11y-label="Add a detail, like color or size"
                    tap="addMetadata"
                >Add a detail, like color or size</x-ui.button>
            </column>
        </column>

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-base text-theme-destructive">
                {{ $error }}
            </text>
        @endif
    </column>
</scroll-view>

@include('native.parent-picker-sheet', [
    'refPrefix' => $formRef,
    'topLevelLabel' => $selectedType === ItemType::Location ? 'Put it with the places in your home' : null,
    'selectedParent' => $selectedParent,
    'browsedParent' => $browsedParent,
    'parentPickerRows' => $parentPickerRows,
    'parentTrail' => $parentTrail,
])
