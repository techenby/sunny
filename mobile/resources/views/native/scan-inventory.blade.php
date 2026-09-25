@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Scan items" back />

@if ($this->unavailableMessage)
    <column ref="scan-unavailable" fill center class="gap-4 bg-theme-background px-8 ios:bg-theme-grouped-background">
        <icon :ios="Ios::Sparkles" :android="Android::AutoAwesome" :size="40" class="text-theme-on-surface-variant" />
        <text class="text-center text-base text-theme-on-surface-variant">{{ $this->unavailableMessage }}</text>
    </column>
@else
    <scroll-view ref="scan-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
        <column class="w-full gap-6 px-6 py-6">
            <text ref="scan-subtitle" class="text-base text-theme-on-surface-variant">
                Photograph a shelf, drawer, or bin. Apple Intelligence lists what it sees, right on your phone.
            </text>

            @include('native.parent-field', [
                'refPrefix' => 'scan',
                'label' => 'Adding to',
                'selectedParent' => $this->selectedParent,
            ])

            <row class="w-full gap-3">
                <button ref="scan-take-photo" variant="primary" icon="camera" class="flex-1" @tap="takePhoto">
                    Take photo
                </button>
                <button ref="scan-choose-photos" variant="secondary" icon="photo" class="flex-1" @tap="choosePhotos">
                    Choose photos
                </button>
            </row>

            @if ($pendingScans !== [])
                <row ref="scan-pending" class="w-full items-center gap-3">
                    <activity-indicator size="small" />
                    <text class="text-sm text-theme-on-surface-variant">
                        {{ trans_choice('Identifying items in :count photo…|Identifying items in :count photos…', count($pendingScans)) }}
                    </text>
                </row>
            @endif

            @if ($scanErrors !== [])
                <column ref="scan-errors" class="w-full gap-1">
                    @foreach ($scanErrors as $scanError)
                        <text class="text-sm text-theme-destructive">{{ $scanError }}</text>
                    @endforeach
                    <pressable ref="scan-errors-dismiss" class="h-10 justify-center self-start" @tap="dismissScanErrors">
                        <text class="text-sm font-semibold text-theme-primary">Dismiss</text>
                    </pressable>
                </column>
            @endif

            @if ($candidates !== [])
                <column class="w-full gap-2">
                    <text class="text-sm font-semibold text-theme-on-surface-variant">
                        {{ trans_choice('Found :count item|Found :count items', count($candidates)) }}
                    </text>

                    <column class="w-full rounded-xl bg-theme-surface">
                        @foreach ($candidates as $candidate)
                            @php
                                $details = collect([
                                    $candidate['brand'],
                                    $candidate['model'],
                                    $candidate['quantity'] > 1 ? '×'.$candidate['quantity'] : null,
                                ])->filter()->implode(' · ');
                            @endphp
                            <row ref="scan-candidate-{{ $candidate['id'] }}" class="w-full items-center gap-3 px-4 py-3">
                                <checkbox
                                    ref="scan-candidate-{{ $candidate['id'] }}-selected"
                                    :value="$candidate['selected']"
                                    a11y-label="Add {{ $candidate['name'] }}"
                                    @change="toggleCandidate({{ $candidate['id'] }})"
                                />
                                <image
                                    ref="scan-candidate-{{ $candidate['id'] }}-photo"
                                    :src="$candidate['photoPath']"
                                    :alt="'Photo '.$candidate['name'].' was found in'"
                                    class="h-14 w-14 rounded-lg object-cover"
                                />
                                <column class="flex-1 gap-1">
                                    <outlined-text-input
                                        ref="scan-candidate-{{ $candidate['id'] }}-name"
                                        a11y-label="Name"
                                        autocapitalize="sentences"
                                        :value="$candidate['name']"
                                        sync-mode="blur"
                                        @change="renameCandidate({{ $candidate['id'] }})"
                                    />
                                    @if ($details !== '')
                                        <text class="text-sm text-theme-on-surface-variant">{{ $details }}</text>
                                    @endif
                                    @if ($candidate['existingMatch'])
                                        <text class="text-sm text-theme-on-surface-variant">Might already be here as “{{ $candidate['existingMatch'] }}”</text>
                                    @endif
                                    @if ($candidate['error'])
                                        <text ref="scan-candidate-{{ $candidate['id'] }}-error" class="text-sm text-theme-destructive">{{ $candidate['error'] }}</text>
                                    @endif
                                </column>
                            </row>
                            @if (! $loop->last)
                                <divider class="ml-14" />
                            @endif
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($error !== '')
                <text ref="scan-error" class="text-sm text-theme-destructive">{{ $error }}</text>
            @endif

            @if ($candidates !== [])
                <button
                    ref="scan-submit"
                    variant="primary"
                    size="lg"
                    font="semibold"
                    class="w-full"
                    :disabled="$saving || $this->selectedCount === 0"
                    :loading="$saving"
                    @tap="save"
                >
                    {{ trans_choice('Add :count item|Add :count items', $this->selectedCount) }}
                </button>
            @endif
        </column>
    </scroll-view>

    @include('native.parent-picker-sheet', [
        'refPrefix' => 'scan',
        'selectedParent' => $this->selectedParent,
        'browsedParent' => $this->browsedParent,
        'parentPickerRows' => $this->parentPickerRows,
    ])
@endif
