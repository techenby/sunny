@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Bulk add" back />

<native:bottom-bar>
    <column class="w-full gap-3 px-4 pb-2">
        <row class="w-full flex-wrap gap-3">
            <pressable
                ref="scan-take-photos"
                a11y-label="Take photos"
                class="min-h-12 min-w-[150] grow items-center justify-center rounded-full px-4 glass android:bg-theme-surface"
                @tap="takePhotos"
            >
                <row class="items-center gap-2">
                    <native:icon :ios="Ios::CameraFill" :android="Android::PhotoCamera" :size="20" class="text-theme-primary-text" />
                    <text font="semibold" class="text-base text-theme-primary-text">Take photos</text>
                </row>
            </pressable>
            <pressable
                ref="scan-choose-photos"
                a11y-label="Choose photos"
                class="min-h-12 min-w-[150] grow items-center justify-center rounded-full px-4 glass android:bg-theme-surface"
                @tap="choosePhotos"
            >
                <row class="items-center gap-2">
                    <native:icon :ios="Ios::Photo" :android="Android::Photo" :size="20" class="text-theme-on-surface" />
                    <text font="semibold" class="text-base text-theme-on-surface">Choose photos</text>
                </row>
            </pressable>
        </row>
        <button
            ref="scan-submit"
            variant="primary"
            font="semibold"
            class="h-12 w-full"
            :disabled="$saving || $candidates === []"
            :loading="$saving"
            @tap="confirmSave"
        >
            {{ $candidates === [] ? 'Add items' : trans_choice('Add :count item|Add :count items', count($candidates)) }}
        </button>
    </column>
</native:bottom-bar>

<scroll-view ref="scan-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 pt-2 pb-32">
        <text ref="scan-subtitle" class="text-base text-theme-on-surface-variant">
            @if ($this->canIdentify)
                Take a photo of each thing. Sunny suggests a name while you keep going.
            @else
                Take a photo of each thing, then give each one a name.
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
                                            <icon :ios="Ios::Sparkles" :android="Android::AutoAwesome" :size="14" class="text-theme-primary-text" />
                                            <text class="text-sm font-semibold text-theme-primary-text">Use “{{ $candidate['suggestedName'] }}”</text>
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
                                ref="scan-candidate-{{ $candidate['id'] }}-actions"
                                a11y-label="Retake or remove"
                                class="h-12 w-12 items-center justify-center"
                                :menu="$this->candidateMenu($candidate['id'])"
                            >
                                <icon :ios="Ios::Ellipsis" :android="Android::MoreHoriz" :size="18" class="text-theme-on-surface-variant" />
                            </pressable>
                        </row>
                        @if (! $loop->last)
                            <divider key="candidate-{{ $candidate['id'] }}-divider" class="ml-20" />
                        @endif
                    @endforeach
                </column>
            </column>

            <pressable
                ref="scan-start-over"
                a11y-label="Start over"
                a11y-hint="Clears these photos and names"
                class="min-h-12 w-full items-center justify-center"
                @tap="confirmStartOver"
            >
                <text font="semibold" class="text-center text-lg text-theme-destructive">Start over</text>
            </pressable>
        @endif
    </column>
</scroll-view>

@include('native.parent-picker-sheet', [
    'refPrefix' => 'scan',
    'selectedParent' => $this->selectedParent,
    'browsedParent' => $this->browsedParent,
    'parentPickerRows' => $this->parentPickerRows,
    'parentTrail' => $this->parentTrail,
])
