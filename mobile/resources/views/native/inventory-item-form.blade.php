@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     $typeChoices, $selectedType, $selectedParent, $browsedParent,
     $parentPickerRows and $parentTrail; the remaining state comes from the
     including component's ManagesInventoryItemForm properties. Computed
     values have to be passed in because an @include is not bound to $this. --}}

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-7 px-5 py-6">
        <column class="w-full gap-2">
            <text font="semibold" class="text-lg text-theme-on-surface">What is it called?</text>
            <outlined-text-input
                ref="{{ $formRef }}-name"
                placeholder="Camping tent"
                a11y-label="What is it called?"
                autocapitalize="sentences"
                native:model.blur="name"
            />
        </column>

        @include('native.parent-field', [
            'refPrefix' => $formRef,
            'selectedParent' => $selectedParent,
            'noParentLabel' => $selectedType === ItemType::Location ? 'Home' : null,
        ])

        <column class="w-full gap-2">
            <column class="w-full gap-0.5">
                <text font="semibold" class="text-lg text-theme-on-surface">What kind is it?</text>
                <x-ui.text size="sm">Not sure? Leave it as a thing.</x-ui.text>
            </column>
            @foreach ($typeChoices as $type)
                <pressable
                    ref="{{ $formRef }}-type-{{ $type->value }}"
                    :a11y-label="$type->label().($type === $selectedType ? ', selected' : '')"
                    :a11y-hint="$type->description()"
                    class="min-h-12 w-full justify-center rounded-xl border px-4 py-2 {{ $type === $selectedType ? 'border-theme-primary bg-theme-primary/10' : 'border-theme-outline bg-theme-surface' }}"
                    @tap="chooseType('{{ $type->value }}')"
                >
                    <row class="w-full items-center gap-4">
                        <x-ui.avatar :color="$type->color()" :ios="$type->iosIcon()" :android="$type->androidIcon()" />
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">{{ $type->label() }}</x-ui.heading>
                            <x-ui.text>{{ $type->description() }}</x-ui.text>
                        </column>
                        @if ($type === $selectedType)
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

        @include('native.photo-field', [
            'photoPath' => $photoPath ?? $existingPhotoUrl,
            'refPrefix' => $formRef,
            'alt' => 'The photo chosen for this item',
            'label' => 'Photo (optional)',
            'labelClass' => 'text-lg font-semibold text-theme-on-surface',
        ])

        <column class="w-full gap-2">
            <text font="semibold" class="text-lg text-theme-on-surface">Extra details (optional)</text>

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
                        <pressable
                            ref="metadata-remove-{{ $index }}"
                            class="h-12 w-12 items-center justify-center"
                            a11y-label="Remove detail {{ $index + 1 }}"
                            @tap="removeMetadata({{ $index }})"
                        >
                            <native:icon :ios="Ios::Xmark" :android="Android::Close" :size="18" class="text-theme-on-surface-variant" />
                        </pressable>
                    </row>
                    <divider class="ml-3" />
                @endforeach

                <pressable
                    ref="metadata-add"
                    a11y-label="Add a detail, like color or size"
                    class="min-h-12 w-full justify-center px-4"
                    @tap="addMetadata"
                >
                    <row class="items-center gap-2">
                        <icon :ios="Ios::Plus" :android="Android::Add" :size="16" class="text-theme-primary" />
                        <text font="semibold" class="text-base text-theme-primary">Add a detail, like color or size</text>
                    </row>
                </pressable>
            </column>
        </column>

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-base text-theme-destructive">
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

@include('native.parent-picker-sheet', [
    'refPrefix' => $formRef,
    'topLevelLabel' => $selectedType === ItemType::Location ? 'Put it with the places in your home' : null,
    'selectedParent' => $selectedParent,
    'browsedParent' => $browsedParent,
    'parentPickerRows' => $parentPickerRows,
    'parentTrail' => $parentTrail,
])
