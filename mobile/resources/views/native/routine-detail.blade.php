@use('App\Icons\Android')
@use('App\Icons\Ios')

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
            <pressable
                ref="routine-add-step"
                a11y-label="Add step"
                class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
                @tap="addStep"
            >
                <native:icon :ios="Ios::Plus" :android="Android::Add" :size="22" class="text-theme-on-surface" />
            </pressable>
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
                    <pressable
                        ref="routine-reorder-done"
                        a11y-label="Done"
                        a11y-hint="Saves the new order"
                        class="min-h-12 items-center justify-center rounded-full bg-theme-primary/15 px-4"
                        @tap="finishReordering"
                    >
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::Checkmark" :android="Android::Check" :size="18" class="text-theme-primary-text" />
                            <text font="semibold" class="text-base text-theme-primary-text">Done</text>
                        </row>
                    </pressable>
                @elseif (count($this->steps) > 1)
                    <pressable
                        ref="routine-reorder"
                        a11y-label="Reorder"
                        a11y-hint="Move steps up or down"
                        class="min-h-12 items-center justify-center rounded-full bg-theme-surface-variant px-4"
                        @tap="startReordering"
                    >
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::ArrowUpArrowDown" :android="Android::SwapVert" :size="18" class="text-theme-on-surface" />
                            <text font="semibold" class="text-base text-theme-on-surface">Reorder</text>
                        </row>
                    </pressable>
                @endif
            </row>

            @if ($this->steps && $reordering)
                <column class="w-full rounded-xl bg-theme-surface">
                    @foreach ($this->steps as $step)
                        @php($moved = $movedStepId === $step['id'])
                        <row
                            ref="routine-step-{{ $step['id'] }}"
                            class="w-full items-center gap-3 py-1 pr-2 pl-4 {{ $moved ? 'bg-theme-primary/10' : '' }} {{ $loop->first ? 'rounded-t-xl' : '' }} {{ $loop->last ? 'rounded-b-xl' : '' }}"
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
                <column class="w-full rounded-xl bg-theme-surface">
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
                                <pressable
                                    ref="routine-step-{{ $step['id'] }}-save"
                                    a11y-label="Save step"
                                    class="h-12 w-12 items-center justify-center"
                                    @tap="renameStep"
                                >
                                    <icon :ios="Ios::Checkmark" :android="Android::Check" :size="20" class="text-theme-primary" />
                                </pressable>
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
                            <pressable
                                ref="routine-step-{{ $step['id'] }}-remove"
                                :a11y-label="'Remove '.$step['name']"
                                class="h-12 w-12 items-center justify-center"
                                @tap="removeStep({{ $step['id'] }})"
                            >
                                <icon :ios="Ios::Trash" :android="Android::Delete" :size="18" class="text-theme-on-surface-variant" />
                            </pressable>
                        </row>
                        @unless ($loop->last)
                            <divider class="ml-[52]" />
                        @endunless
                    @endforeach
                </column>
            @else
                <column ref="routine-empty" class="w-full items-center gap-2 rounded-xl bg-theme-surface px-4 py-6">
                    <icon :ios="Ios::ChecklistChecked" :android="Android::Checklist" :size="26" class="text-theme-primary" />
                    <text font="semibold" class="text-base text-theme-on-surface">This routine has no steps.</text>
                    <text class="text-sm text-theme-on-surface-variant">Add a step below.</text>
                </column>
            @endif
        </column>
    </scroll-view>
@else
    <x-ui.not-found ref="routine-missing" thing="routine" />
@endif
