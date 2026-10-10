@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Scan items" back>
    @if ($candidates !== [])
        <native:top-bar-action
            ref="scan-start-over"
            id="scan-start-over"
            label="Start over"
            :ios-icon="Ios::Trash"
            :android-icon="Android::Delete"
            @tap="confirmStartOver"
        />
    @endif
</native:top-bar>

<native:bottom-bar>
    <row class="w-full items-center gap-3 px-4 pb-2">
        <pressable
            ref="scan-choose-photos"
            a11y-label="Choose photos"
            class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
            @tap="choosePhotos"
        >
            <native:icon :ios="Ios::Photo" :android="Android::Photo" :size="22" class="text-theme-on-surface" />
        </pressable>
        <pressable
            ref="scan-take-photos"
            a11y-label="Take photos"
            class="h-12 w-12 items-center justify-center rounded-full bg-theme-primary"
            @tap="takePhotos"
        >
            <native:icon :ios="Ios::CameraFill" :android="Android::PhotoCamera" :size="22" class="text-theme-on-primary" />
        </pressable>
        <button
            ref="scan-submit"
            variant="primary"
            font="semibold"
            class="h-12 flex-1"
            :disabled="$saving || $candidates === []"
            :loading="$saving"
            @tap="confirmSave"
        >
            {{ $candidates === [] ? 'Add items' : trans_choice('Add :count item|Add :count items', count($candidates)) }}
        </button>
    </row>
</native:bottom-bar>

<scroll-view ref="scan-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 pt-2 pb-32">
        <text ref="scan-subtitle" class="text-base text-theme-on-surface-variant">
            @if ($this->canIdentify)
                Tap the camera and photograph each item in turn. Apple Intelligence names them in the background while you keep shooting.
            @else
                Tap the camera and photograph each item in turn, then give each one a name.
            @endif
        </text>

        @include('native.parent-field', [
            'refPrefix' => 'scan',
            'label' => 'Adding to',
            'selectedParent' => $this->selectedParent,
        ])

        @if ($this->canIdentify)
            <outlined-text-input
                ref="scan-batch"
                label="What are you scanning?"
                placeholder="Christmas ornaments"
                autocapitalize="sentences"
                :value="$batch"
                sync-mode="blur"
                @change="updateBatch"
            />
        @endif

        @if ($error !== '')
            <text ref="scan-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        @if ($candidates !== [])
            <column class="w-full gap-2">
                <text ref="scan-count" class="text-sm font-semibold text-theme-on-surface-variant">
                    {{ trans_choice(':count item|:count items', count($candidates)) }}@if ($this->identifyingCount > 0) · {{ $this->identifyingCount }} identifying…@endif
                </text>

                <column class="w-full rounded-xl bg-theme-surface">
                    @foreach ($candidates as $candidate)
                        @php
                            $details = collect([
                                $candidate['category'],
                                $this->quantityOf($candidate) > 1 ? '×'.$this->quantityOf($candidate) : null,
                            ])->filter()->implode(' · ');
                        @endphp
                        <row key="candidate-{{ $candidate['id'] }}" ref="scan-candidate-{{ $candidate['id'] }}" class="w-full items-center gap-3 py-3 pl-4">
                            <image
                                ref="scan-candidate-{{ $candidate['id'] }}-photo"
                                :src="$candidate['photoPath']"
                                alt="Item photo"
                                class="h-14 w-14 rounded-lg object-cover"
                            />
                            <column class="flex-1 gap-1">
                                <outlined-text-input
                                    ref="scan-candidate-{{ $candidate['id'] }}-name"
                                    a11y-label="Name"
                                    :placeholder="$candidate['suggestedName'] ?: ($candidate['scanId'] ? 'Identifying…' : 'Name')"
                                    autocapitalize="sentences"
                                    :value="$candidate['name']"
                                    sync-mode="blur"
                                    @change="renameCandidate({{ $candidate['id'] }})"
                                />
                                @if ($candidate['suggestedName'] !== '' && trim($candidate['name']) === '')
                                    <pressable
                                        ref="scan-candidate-{{ $candidate['id'] }}-use-suggestion"
                                        :a11y-label="'Use '.$candidate['suggestedName'].' as the name'"
                                        class="h-8 justify-center self-start"
                                        @tap="useSuggestion({{ $candidate['id'] }})"
                                    >
                                        <row class="items-center gap-1">
                                            <icon :ios="Ios::Sparkles" :android="Android::AutoAwesome" :size="14" class="text-theme-primary" />
                                            <text class="text-sm font-semibold text-theme-primary">Use “{{ $candidate['suggestedName'] }}”</text>
                                        </row>
                                    </pressable>
                                @endif
                                @if ($candidate['scanId'])
                                    <row ref="scan-candidate-{{ $candidate['id'] }}-identifying" class="items-center gap-2">
                                        <activity-indicator size="small" />
                                        <text class="text-sm text-theme-on-surface-variant">Identifying…</text>
                                    </row>
                                @endif
                                @if ($details !== '')
                                    <text class="text-sm text-theme-on-surface-variant">{{ $details }}</text>
                                @endif
                                @if ($candidate['scanError'])
                                    <text ref="scan-candidate-{{ $candidate['id'] }}-scan-error" class="text-sm text-theme-on-surface-variant">{{ $candidate['scanError'] }}</text>
                                @endif
                                @if ($candidate['error'])
                                    <text ref="scan-candidate-{{ $candidate['id'] }}-error" class="text-sm text-theme-destructive">{{ $candidate['error'] }}</text>
                                @endif
                            </column>
                            <pressable
                                ref="scan-candidate-{{ $candidate['id'] }}-retake"
                                a11y-label="Retake photo"
                                class="h-12 w-10 items-center justify-center"
                                @tap="retakePhoto({{ $candidate['id'] }})"
                            >
                                <icon :ios="Ios::Camera" :android="Android::PhotoCamera" :size="18" class="text-theme-on-surface-variant" />
                            </pressable>
                            <pressable
                                ref="scan-candidate-{{ $candidate['id'] }}-remove"
                                a11y-label="Remove item"
                                class="h-12 w-12 items-center justify-center"
                                @tap="removeCandidate({{ $candidate['id'] }})"
                            >
                                <icon :ios="Ios::Trash" :android="Android::Delete" :size="18" class="text-theme-on-surface-variant" />
                            </pressable>
                        </row>
                        @if (! $loop->last)
                            <divider key="candidate-{{ $candidate['id'] }}-divider" class="ml-20" />
                        @endif
                    @endforeach
                </column>
            </column>
        @endif
    </column>
</scroll-view>

@include('native.parent-picker-sheet', [
    'refPrefix' => 'scan',
    'selectedParent' => $this->selectedParent,
    'browsedParent' => $this->browsedParent,
    'parentPickerRows' => $this->parentPickerRows,
])
