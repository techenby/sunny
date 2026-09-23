@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="New item" back />

<scroll-view ref="create-item-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        <text ref="create-item-subtitle" class="text-base text-theme-on-surface-variant">
            Name it, pick what kind of thing it is, and choose where it lives.
        </text>

        <column class="w-full gap-4">
            <outlined-text-input
                ref="create-item-name"
                label="Name"
                placeholder="Camping tent"
                autocapitalize="sentences"
                :ios-leading-icon="Ios::Tag"
                :android-leading-icon="Android::Label"
                native:model.blur="name"
            />

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Photo</text>

                @if ($photoPath)
                    <image
                        ref="create-item-photo"
                        :src="$photoPath"
                        alt="The photo chosen for this item"
                        class="w-full rounded-xl"
                        :height="180"
                        :fit="2"
                    />
                    <pressable
                        ref="create-item-photo-remove"
                        class="h-12 items-start justify-center self-start px-2"
                        @tap="removePhoto"
                    >
                        <text class="text-sm font-semibold text-theme-primary">Remove photo</text>
                    </pressable>
                @else
                    <row class="w-full items-center gap-3">
                        <pressable
                            ref="create-item-photo-camera"
                            a11y-label="Take photo"
                            class="h-12 w-12 items-center justify-center rounded-xl border border-theme-outline bg-theme-surface-variant"
                            @tap="takePhoto"
                        >
                            <native:icon
                                :ios="Ios::Camera"
                                :android="Android::PhotoCamera"
                                :size="24"
                                class="text-theme-on-surface-variant"
                            />
                        </pressable>
                        <pressable
                            ref="create-item-photo-library"
                            class="h-12 items-center justify-center px-2"
                            a11y-hint="Opens the photo library"
                            @tap="choosePhoto"
                        >
                            <text class="text-sm font-semibold text-theme-primary">View Library</text>
                        </pressable>
                    </row>
                @endif
            </column>

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Type</text>
                <button-group
                    ref="create-item-type"
                    :options="$this->typeOptions"
                    a11y-label="Item type"
                    native:model="typeIndex"
                />
            </column>

            <select
                ref="create-item-parent"
                label="Inside"
                :options="$this->parentOptions"
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
            <text ref="create-item-error" class="text-sm text-theme-destructive">
                {{ $error }}
            </text>
        @endif

        <button
            ref="create-item-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            @tap="save"
        >
            Save item
        </button>
    </column>
</scroll-view>
