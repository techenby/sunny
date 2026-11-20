@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\CookRecipe')
@use('App\Ui\Card')

@php
    $recipe = $this->recipe;
    $timer = $this->timer;
    $onPrep = $tab === CookRecipe::TAB_PREP;
@endphp

<native:top-bar title="Cook mode" back />

@if ($recipe)
    <native:bottom-bar>
        <column class="w-full gap-3 px-4 pb-2">
            @if ($timer)
                <row ref="cook-timer-dock" native:poll="1s" class="w-full items-center gap-3 rounded-[24] bg-theme-on-surface py-2 pr-2 pl-5">
                    <column class="flex-1 gap-0.5">
                        <text font="medium" class="text-xs text-theme-surface/70">STEP {{ $timer['step'] }} TIMER</text>
                        <text :max-lines="1" class="text-base text-theme-surface">{{ $timer['text'] }}</text>
                    </column>
                    <x-ui.button
                        ref="cook-timer-stop"
                        variant="secondary"
                        :ios="Ios::StopFill"
                        :android="Android::Stop"
                        :a11y-label="'Stop the step '.$timer['step'].' timer, '.CookRecipe::clock($timer['remaining']).' left'"
                        tap="stopTimer"
                    >Stop timer {{ CookRecipe::clock($timer['remaining']) }}</x-ui.button>
                </row>
            @endif

            <row class="w-full items-center gap-3">
                @if ($onPrep)
                    @if ($this->stepCount)
                        <x-ui.button
                            ref="cook-go-to-steps"
                            class="flex-1"
                            :ios="Ios::ArrowRight"
                            :android="Android::ArrowForward"
                            icon-trailing
                            tap="goToStep({{ $step }})"
                        >{{ $step === 1 ? 'Start cooking' : 'Back to step '.$step }}</x-ui.button>
                    @endif
                @else
                    @if ($step > 1)
                        <x-ui.button
                            ref="cook-previous-step"
                            variant="glass"
                            :ios="Ios::ChevronLeft"
                            :android="Android::ChevronLeft"
                            a11y-label="Previous step"
                            tap="previousStep"
                        />
                    @endif
                    <x-ui.button
                        ref="cook-next-step"
                        class="flex-1"
                        :ios="Ios::ArrowRight"
                        :android="Android::ArrowForward"
                        icon-trailing
                        tap="nextStep"
                    >{{ $step >= $this->stepCount ? 'Done cooking' : 'Next: step '.($step + 1) }}</x-ui.button>
                @endif
            </row>
        </column>
    </native:bottom-bar>

    <scroll-view ref="cook-recipe" fill :auto-scroll-to="$this->scrollTarget" class="gap-3 bg-theme-background">
        <column class="h-[1] w-full" />

        <column class="w-full gap-4 px-4">
            <column class="w-full gap-1">
                <text ref="cook-recipe-name" font="accent" class="text-xl text-theme-on-background">{{ $recipe['name'] }}</text>
                @if ($staysAwake)
                    <row ref="cook-stays-awake" class="items-center gap-1.5">
                        <icon :ios="Ios::SunMax" :android="Android::LightMode" :size="14" class="text-theme-primary-text" />
                        <x-ui.text size="sm">Screen stays on</x-ui.text>
                    </row>
                @endif
            </column>

            <row ref="cook-tabs" class="w-full gap-1 rounded-[18] bg-theme-surface-variant p-1">
                @foreach ([
                    CookRecipe::TAB_PREP => ['Prep', $this->checkedCount.'/'.$this->ingredientCount],
                    CookRecipe::TAB_COOK => ['Cook', $step.'/'.$this->stepCount],
                ] as $index => [$label, $count])
                    <pressable
                        ref="cook-tab-{{ strtolower($label) }}"
                        @press="showTab({{ $index }})"
                        :a11y-label="$label.', '.$count.($tab === $index ? ', selected' : '')"
                        class="min-h-12 flex-1 items-center justify-center rounded-[14] {{ $tab === $index ? 'bg-theme-surface' : '' }}"
                    >
                        <row class="items-center gap-2">
                            <text font="semibold" class="text-lg {{ $tab === $index ? 'text-theme-on-surface' : 'text-theme-on-surface-variant' }}">{{ $label }}</text>
                            <text font="medium" class="rounded-full px-2 py-0.5 text-xs {{ $tab === $index ? 'bg-theme-primary/15 text-theme-primary-text' : 'bg-theme-on-surface-variant/15 text-theme-on-surface-variant' }}">{{ $count }}</text>
                        </row>
                    </pressable>
                @endforeach
            </row>
        </column>

        @if ($onPrep)
            @if ($servings !== null)
                <column class="w-full px-4">
                    <row ref="cook-servings" class="{{ Card::classes() }} w-full items-center gap-3 px-4 py-3">
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading>Servings</x-ui.heading>
                            <x-ui.text size="sm">Amounts scale to match</x-ui.text>
                        </column>
                        <x-ui.button
                            ref="cook-fewer-servings"
                            variant="secondary"
                            :ios="Ios::Minus"
                            :android="Android::Remove"
                            a11y-label="Fewer servings"
                            tap="changeServings(-1)"
                        />
                        <text ref="cook-servings-count" font="accent" class="w-10 text-center text-xl text-theme-on-surface">{{ $servings }}</text>
                        <x-ui.button
                            ref="cook-more-servings"
                            variant="secondary"
                            :ios="Ios::Plus"
                            :android="Android::Add"
                            a11y-label="More servings"
                            tap="changeServings(1)"
                        />
                    </row>
                </column>
            @endif

            @if ($this->ingredients)
                <column class="w-full gap-3 px-4 pt-1 pb-8">
                    <row class="w-full items-center">
                        <x-ui.heading size="lg" class="flex-1">Ingredients</x-ui.heading>
                        <x-ui.text size="sm">{{ $this->checkedCount }} of {{ $this->ingredientCount }} ready</x-ui.text>
                    </row>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->ingredients as $position => $ingredient)
                            @if ($this->isHeading($ingredient))
                                <text ref="cook-ingredient-heading-{{ $ingredient['index'] }}" font="medium" class="w-full px-4 pt-4 pb-1 text-xs text-theme-on-surface-variant">{{ mb_strtoupper($ingredient['text']) }}</text>
                                @continue
                            @endif
                            <pressable
                                ref="cook-ingredient-{{ $ingredient['index'] }}"
                                @press="toggleIngredient({{ $ingredient['index'] }})"
                                :a11y-label="($ingredient['checked'] ? 'Checked: ' : '').trim($ingredient['display']['amount'].' '.$ingredient['display']['text'])"
                                class="min-h-12 w-full justify-center px-4 py-2"
                            >
                                <row class="w-full items-center gap-3">
                                    <icon
                                        :ios="$ingredient['checked'] ? Ios::CheckmarkCircleFill : Ios::Circle"
                                        :android="$ingredient['checked'] ? Android::CheckCircle : Android::RadioButtonUnchecked"
                                        :size="26"
                                        :class="$ingredient['checked'] ? 'text-theme-primary' : 'text-theme-outline'"
                                    />
                                    @if ($ingredient['checked'])
                                        <text class="flex-1 text-base text-theme-on-surface-variant line-through">{{ trim($ingredient['display']['amount'].' '.$ingredient['display']['text']) }}</text>
                                    @elseif ($ingredient['display']['amount'])
                                        <text class="flex-1 text-base text-theme-on-surface"><text font="semibold" class="text-base text-theme-on-surface">{{ $ingredient['display']['amount'] }}</text> {{ $ingredient['display']['text'] }}</text>
                                    @else
                                        <text class="flex-1 text-base text-theme-on-surface">{{ $ingredient['display']['text'] }}</text>
                                    @endif
                                </row>
                            </pressable>
                            @unless ($loop->last || $this->isHeading($this->ingredients[$position + 1]))
                                <divider class="ml-[54]" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif
        @else
            @foreach ($this->steps as $row)
                @if ($this->isHeading($row))
                    <column class="w-full px-4 pt-2">
                        <x-ui.heading ref="cook-step-heading-{{ $loop->index }}">{{ $row['text'] }}</x-ui.heading>
                    </column>
                    @continue
                @endif
                @php
                    $number = $row['number'];
                    $isCurrent = $number === $step;
                    $isDone = $number < $step;
                    $running = $timer && $timer['step'] === $number ? $timer : null;
                @endphp
            <column class="w-full px-4">
                <column
                    ref="cook-step-{{ $number }}"
                    class="w-full gap-3 rounded-[18] bg-theme-surface p-4 {{ $isCurrent ? 'border-2 border-theme-primary' : 'border border-theme-on-surface/10' }}"
                >
                    <pressable
                        ref="cook-step-{{ $number }}-select"
                        @press="goToStep({{ $number }})"
                        :a11y-label="'Step '.$number.($isCurrent ? ', current step' : ($isDone ? ', done' : '')).': '.$row['text']"
                        class="w-full"
                    >
                        <row class="w-full items-start gap-3">
                            <column class="h-8 w-8 items-center justify-center rounded-full {{ $isCurrent || $isDone ? 'bg-theme-primary' : 'bg-theme-primary/15' }}">
                                @if ($isDone)
                                    <icon :ios="Ios::Checkmark" :android="Android::Check" :size="16" class="text-theme-on-primary" />
                                @else
                                    <text font="accent" class="text-sm {{ $isCurrent ? 'text-theme-on-primary' : 'text-theme-primary-text' }}">{{ $number }}</text>
                                @endif
                            </column>
                            <column class="flex-1 gap-2">
                                @if ($isCurrent)
                                    <x-ui.badge color="red" size="sm" class="self-start">Current step</x-ui.badge>
                                @endif
                                <text class="{{ $isCurrent ? 'text-xl text-theme-on-surface' : ($isDone ? 'text-base text-theme-on-surface-variant' : 'text-base text-theme-on-surface') }}">{{ $row['text'] }}</text>
                            </column>
                        </row>
                    </pressable>

                    @if ($running)
                        <column ref="cook-step-{{ $number }}-progress" class="w-full gap-2 pl-11">
                            <native:progress-bar :value="$running['progress']" :a11y-label="CookRecipe::clock($running['remaining']).' left'" />
                            <row class="items-center gap-2">
                                <icon :ios="Ios::Timer" :android="Android::Timer" :size="16" class="text-theme-primary-text" />
                                <text font="semibold" class="text-sm text-theme-primary-text">{{ CookRecipe::clock($running['remaining']) }}</text>
                                <x-ui.text size="sm">left of {{ CookRecipe::clock($running['total']) }}</x-ui.text>
                            </row>
                        </column>
                    @elseif ($row['next'] !== null)
                        <row class="w-full items-center gap-3 pl-11">
                            <x-ui.button
                                ref="cook-step-{{ $number }}-timer"
                                variant="secondary"
                                :ios="Ios::PlayFill"
                                :android="Android::PlayArrow"
                                :a11y-label="'Start a '.CookRecipe::minutes($row['next']).' timer for step '.$number"
                                tap="toggleTimer({{ $number }})"
                            >Start timer {{ CookRecipe::clock($row['next']) }}</x-ui.button>
                            @if ($row['upcoming'])
                                <x-ui.text size="sm">then {{ collect($row['upcoming'])->map(fn (int $seconds): string => CookRecipe::minutes($seconds))->implode(', ') }}</x-ui.text>
                            @endif
                        </row>
                    @endif
                </column>
            </column>
            @endforeach

            <column class="h-8 w-full" />
        @endif
    </scroll-view>
@else
    <x-ui.not-found ref="cook-recipe-missing" thing="recipe" />
@endif
