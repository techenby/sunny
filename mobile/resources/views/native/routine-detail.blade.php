@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

@php($routine = $this->routine)

<native:top-bar :title="$routine['name'] ?? 'Routine'" back>
    @if ($routine)
        <native:top-bar-action
            ref="edit-routine"
            id="edit-routine"
            label="Edit"
            :ios-icon="Ios::Pencil"
            :android-icon="Android::Edit"
            @navigate('/routines/'.$routine['id'].'/edit')
        />
        <native:top-bar-action
            ref="delete-routine"
            id="delete-routine"
            label="Delete"
            :ios-icon="Ios::Trash"
            :android-icon="Android::Delete"
            @tap="confirmDeleteRoutine"
        />
    @endif
</native:top-bar>

@if ($routine)
    <native:bottom-bar>
        <row class="w-full items-center gap-3 px-4 pb-2">
            <row class="h-12 flex-1 items-center gap-2 rounded-full px-4 glass android:bg-theme-surface">
                <bare-text-input
                    ref="routine-new-step"
                    class="flex-1"
                    placeholder="Add a step"
                    a11y-label="Add a step"
                    autocapitalize="sentences"
                    keep-focus-on-submit
                    native:model="newStep"
                    @submit="addStep"
                />
            </row>
            <x-ui.button ref="routine-add-step" variant="glass" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add step" tap="addStep" />
        </row>
    </native:bottom-bar>

    <scroll-view ref="routine-detail" fill class="bg-theme-background">
        <column class="w-full gap-4 px-4 pt-2 pb-8">
            <row class="w-full items-start gap-2">
                <icon :ios="$routine['time_of_day']->iosIcon()" :android="$routine['time_of_day']->androidIcon()" :size="16" class="mt-0.5 text-theme-on-surface-variant" />
                <text ref="routine-summary" class="flex-1 text-sm text-theme-on-surface-variant">{{ $routine['summary'] }}</text>
            </row>

            @include('native.queued-change', ['refPrefix' => 'routine', 'queuedChange' => $this->queuedChange])

            @if ($error !== '')
                <text ref="routine-error" class="text-sm text-theme-destructive">{{ $error }}</text>
            @endif

            <row class="w-full items-center gap-3 px-1">
                <x-ui.heading size="lg" class="flex-1">{{ $this->steps ? 'Steps ('.count($this->steps).')' : 'Steps' }}</x-ui.heading>
                @if ($reordering)
                    <x-ui.button
                        ref="routine-reorder-done"
                        variant="tint"
                        :ios="Ios::Checkmark"
                        :android="Android::Check"
                        :icon-size="18"
                        a11y-label="Done"
                        a11y-hint="Saves the new order"
                        tap="finishReordering"
                    >Done</x-ui.button>
                @elseif (count($this->steps) > 1)
                    <x-ui.button
                        ref="routine-reorder"
                        variant="neutral"
                        :ios="Ios::ArrowUpArrowDown"
                        :android="Android::SwapVert"
                        :icon-size="18"
                        a11y-label="Reorder"
                        a11y-hint="Move steps up or down"
                        tap="startReordering"
                    >Reorder</x-ui.button>
                @endif
            </row>

            @if ($this->steps && $reordering)
                <column class="w-full {{ Card::classes() }}">
                    @foreach ($this->steps as $step)
                        @php($moved = $movedStepId === $step['id'])
                        <row
                            ref="routine-step-{{ $step['id'] }}"
                            class="w-full items-center gap-3 py-1 pr-2 pl-4 {{ $moved ? 'bg-theme-primary/10' : '' }} {{ $loop->first ? 'rounded-t-[17]' : '' }} {{ $loop->last ? 'rounded-b-[17]' : '' }}"
                        >
                            @if ($moved)
                                <text font="semibold" class="w-6 text-center text-sm text-theme-primary-text">{{ $loop->iteration }}</text>
                            @else
                                <text class="w-6 text-center text-sm text-theme-on-surface-variant">{{ $loop->iteration }}</text>
                            @endif
                            <text class="flex-1 text-base text-theme-on-surface">{{ $step['name'] }}</text>
                            @foreach ([['up', 'moveStepUp', Ios::ChevronUp, Android::KeyboardArrowUp, $loop->first], ['down', 'moveStepDown', Ios::ChevronDown, Android::KeyboardArrowDown, $loop->last]] as [$direction, $action, $iosIcon, $androidIcon, $atEnd])
                                @if ($atEnd)
                                    <column ref="routine-step-{{ $step['id'] }}-{{ $direction }}" class="h-12 w-11 items-center justify-center">
                                        <column class="h-10 w-10 items-center justify-center rounded-full border border-theme-outline opacity-40">
                                            <icon :ios="$iosIcon" :android="$androidIcon" :size="18" class="text-theme-on-surface-variant" />
                                        </column>
                                    </column>
                                @else
                                    <pressable
                                        ref="routine-step-{{ $step['id'] }}-{{ $direction }}"
                                        :a11y-label="'Move '.$step['name'].' '.$direction"
                                        class="h-12 w-11 items-center justify-center"
                                        @tap="{{ $action }}({{ $step['id'] }})"
                                    >
                                        <column class="h-10 w-10 items-center justify-center rounded-full bg-theme-surface-variant">
                                            <icon :ios="$iosIcon" :android="$androidIcon" :size="18" class="text-theme-on-surface" />
                                        </column>
                                    </pressable>
                                @endif
                            @endforeach
                        </row>
                        @unless ($loop->last)
                            <divider class="ml-[52]" />
                        @endunless
                    @endforeach
                </column>
            @elseif ($this->steps)
                <column class="w-full {{ Card::classes() }}">
                    @foreach ($this->steps as $step)
                        <row ref="routine-step-{{ $step['id'] }}" class="w-full items-center">
                            @if ($editingStepId === $step['id'])
                                <row class="flex-1 items-center gap-3 py-3 pl-4">
                                    <text class="w-6 text-center text-sm text-theme-on-surface-variant">{{ $loop->iteration }}</text>
                                    <bare-text-input
                                        ref="routine-step-{{ $step['id'] }}-name"
                                        class="flex-1 text-base"
                                        a11y-label="Step name"
                                        autocapitalize="sentences"
                                        autofocus
                                        native:model.debounce.300ms="editingName"
                                        @submit="renameStep"
                                    />
                                </row>
                                <x-ui.button
                                    ref="routine-step-{{ $step['id'] }}-save"
                                    variant="plain"
                                    :ios="Ios::Checkmark"
                                    :android="Android::Check"
                                    :icon-size="20"
                                    icon-class="text-theme-primary"
                                    a11y-label="Save step"
                                    tap="renameStep"
                                />
                            @else
                                <pressable
                                    ref="routine-step-{{ $step['id'] }}-edit"
                                    :a11y-label="'Edit '.$step['name']"
                                    class="flex-1"
                                    @tap="editStep({{ $step['id'] }})"
                                >
                                    <row class="w-full items-start gap-3 py-3 pl-4">
                                        <text class="w-6 pt-0.5 text-center text-sm text-theme-on-surface-variant">{{ $loop->iteration }}</text>
                                        <column class="flex-1 gap-0.5">
                                            <text class="text-base text-theme-on-surface">{{ $step['name'] }}</text>
                                            @if ($step['error'])
                                                <text ref="routine-step-{{ $step['id'] }}-error" class="text-sm text-theme-destructive">Not saved to Sunny. {{ $step['error'] }}</text>
                                            @endif
                                        </column>
                                    </row>
                                </pressable>
                            @endif
                            <x-ui.button
                                ref="routine-step-{{ $step['id'] }}-remove"
                                variant="plain"
                                tone="muted"
                                :ios="Ios::Trash"
                                :android="Android::Delete"
                                :icon-size="18"
                                :a11y-label="'Remove '.$step['name']"
                                tap="removeStep({{ $step['id'] }})"
                            />
                        </row>
                        @unless ($loop->last)
                            <divider class="ml-[52]" />
                        @endunless
                    @endforeach
                </column>
            @else
                <x-ui.empty-state
                    ref="routine-empty"
                    :ios="Ios::ChecklistChecked"
                    :android="Android::Checklist"
                    heading="This routine has no steps."
                    text="Add a step below."
                />
            @endif
        </column>
    </scroll-view>
@else
    <x-ui.not-found ref="routine-missing" thing="routine" />
@endif
