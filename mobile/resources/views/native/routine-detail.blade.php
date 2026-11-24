@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

@php($routine = $this->routine)

<native:top-bar :title="$routine['name'] ?? 'Routine'" display-mode="large" back />

@if ($routine)
    <native:bottom-bar>
        <row class="w-full items-center gap-3 px-4 pb-2">
            <row class="h-12 flex-1 items-center gap-2 rounded-full pr-1 pl-4 glass android:bg-theme-surface">
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
                <x-ui.button ref="routine-add-step" variant="text" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add a step" tap="addStep" />
            </row>
            <x-ui.button ref="routine-more" variant="glass" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$this->moreMenu()" />
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
                        variant="secondary"
                        :ios="Ios::Checkmark"
                        :android="Android::Check"
                        a11y-label="Done"
                        a11y-hint="Saves the new order"
                        tap="finishReordering"
                    >Done</x-ui.button>
                @elseif (count($this->steps) > 1)
                    <x-ui.button
                        ref="routine-reorder"
                        variant="secondary"
                        :ios="Ios::ArrowUpArrowDown"
                        :android="Android::SwapVert"
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
                                <x-ui.button
                                    ref="routine-step-{{ $step['id'] }}-{{ $direction }}"
                                    variant="text"
                                    :ios="$iosIcon"
                                    :android="$androidIcon"
                                    :a11y-label="'Move '.$step['name'].' '.$direction"
                                    :disabled="$atEnd"
                                    tap="{{ $action }}({{ $step['id'] }})"
                                />
                            @endforeach
                        </row>
                        @unless ($loop->last)
                            <x-ui.row-divider leading="number" />
                        @endunless
                    @endforeach
                </column>
            @elseif ($this->steps)
                <column class="w-full {{ Card::classes() }}">
                    @foreach ($this->steps as $step)
                        @if ($editingStepId === $step['id'])
                            <row ref="routine-step-{{ $step['id'] }}" class="w-full items-center pr-1">
                                <row class="min-h-12 flex-1 items-center gap-3 py-2 pl-4">
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
                                    variant="text"
                                    :ios="Ios::Checkmark"
                                    :android="Android::Check"
                                    a11y-label="Save step"
                                    tap="renameStep"
                                />
                            </row>
                            @unless ($loop->last)
                                <x-ui.row-divider leading="number" />
                            @endunless
                        @else
                            <x-ui.row
                                :ref="'routine-step-'.$step['id']"
                                :press-ref="'routine-step-'.$step['id'].'-edit'"
                                :a11y-label="'Edit '.$step['name']"
                                :number="$loop->iteration"
                                :headline="$step['name']"
                                plain
                                :error="$step['error'] ? 'Not saved. '.$step['error'] : null"
                                :error-ref="'routine-step-'.$step['id'].'-error'"
                                :tap="'editStep('.$step['id'].')'"
                                :menu="$this->stepMenu($step)"
                                :menu-label="'More for '.$step['name']"
                                :last="$loop->last"
                            />
                        @endif
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
