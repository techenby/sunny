@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Bulk add" back />

<native:bottom-bar>
    <column class="w-full gap-3 px-4 pb-2">
        <x-ui.button
            ref="scan-take-photos"
            variant="glass"
            class="w-full"
            :ios="Ios::CameraFill"
            :android="Android::PhotoCamera"
            a11y-label="Take photos"
            tap="takePhotos"
        >Take photos</x-ui.button>
        <x-ui.button
            ref="scan-submit"
            class="w-full"
            :disabled="$saving || $candidates === []"
            :loading="$saving"
            tap="confirmSave"
        >
            {{ $candidates === [] ? 'Add items' : trans_choice('Add :count item|Add :count items', count($candidates)) }}
        </x-ui.button>
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

        <x-ui.button
            ref="scan-choose-photos"
            variant="secondary"
            class="w-full"
            :ios="Ios::Photo"
            :android="Android::Photo"
            a11y-label="Choose photos"
            a11y-hint="Pick photos you already took"
            tap="choosePhotos"
        >Choose photos</x-ui.button>

        @if ($error !== '')
            <text ref="scan-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        @if ($candidates !== [])
            <column class="w-full gap-2">
                <text ref="scan-count" class="text-sm font-semibold text-theme-on-surface-variant">
                    {{ trans_choice(':count item|:count items', count($candidates)) }}@if ($this->identifyingCount > 0) · {{ $this->identifyingCount }} identifying…@endif
                </text>

                <column class="w-full {{ Card::classes() }}">
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
                                    <x-ui.button
                                        ref="scan-candidate-{{ $candidate['id'] }}-use-suggestion"
                                        variant="text"
                                        class="self-start"
                                        :ios="Ios::Sparkles"
                                        :android="Android::AutoAwesome"
                                        :a11y-label="'Use '.$candidate['suggestedName'].' as the name'"
                                        tap="useSuggestion({{ $candidate['id'] }})"
                                    >Use “{{ $candidate['suggestedName'] }}”</x-ui.button>
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
                            <x-ui.button
                                ref="scan-candidate-{{ $candidate['id'] }}-actions"
                                variant="text"
                                :ios="Ios::Ellipsis"
                                :android="Android::MoreHoriz"
                                a11y-label="Retake or remove"
                                :menu="$this->candidateMenu($candidate['id'])"
                            />
                        </row>
                        @if (! $loop->last)
                            <divider key="candidate-{{ $candidate['id'] }}-divider" class="ml-[84]" />
                        @endif
                    @endforeach
                </column>
            </column>

            <x-ui.button
                ref="scan-start-over"
                variant="text"
                class="w-full items-center"
                a11y-label="Start over"
                a11y-hint="Clears these photos and names"
                tap="confirmStartOver"
            >Start over</x-ui.button>
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
