@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')
@use('App\NativeComponents\Recipes')

@php($recipe = $this->recipe)

<native:top-bar :title="$recipe['name'] ?? 'Recipe'" back />

@if ($recipe)
    @include('native.action-bottom-bar', ['actions' => [
        ['ref' => 'edit-recipe', 'label' => 'Edit', 'ios' => Ios::Pencil, 'android' => Android::Edit, 'url' => '/recipes/'.$recipe['id'].'/edit'],
    ]])

    <scroll-view ref="recipe-detail" fill class="bg-theme-background">
        <column class="w-full gap-6 px-4 pt-2 pb-8">
            <column class="w-full gap-3">
                @if ($recipe['photo_url'] ?? null)
                    <image
                        ref="recipe-photo"
                        :src="$recipe['photo_url']"
                        :alt="'Photo of '.$recipe['name']"
                        class="w-full h-[240] rounded-[18] object-cover"
                    />
                @else
                    <column class="w-full h-[160] items-center justify-center rounded-[18] bg-theme-primary/15">
                        <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="44" class="text-theme-primary" />
                    </column>
                @endif

                <text ref="recipe-name" font="accent" class="text-3xl text-theme-on-background">{{ $recipe['name'] }}</text>

                @include('native.queued-change', ['refPrefix' => 'recipe', 'queuedChange' => $this->queuedChange])

                @if ($recipe['description'])
                    <text ref="recipe-description" class="text-base text-theme-on-surface-variant">{{ $recipe['description'] }}</text>
                @endif

                @if ($recipe['tags'])
                    <row ref="recipe-tags" class="w-full flex-wrap gap-2">
                        @foreach ($recipe['tags'] as $tag)
                            <x-ui.badge size="sm">{{ $tag }}</x-ui.badge>
                        @endforeach
                    </row>
                @endif
            </column>

            @if ($this->details)
                <column ref="recipe-facts" class="w-full gap-3">
                    @foreach (array_chunk($this->details, 2, true) as $pair)
                        <row class="w-full gap-3">
                            @foreach ($pair as $label => $value)
                                <column class="{{ Card::classes() }} flex-1 gap-0.5 p-4">
                                    <x-ui.text size="sm">{{ $label }}</x-ui.text>
                                    <x-ui.heading>{{ $value }}</x-ui.heading>
                                </column>
                            @endforeach
                            @if (count($pair) === 1)
                                <column class="flex-1" />
                            @endif
                        </row>
                    @endforeach
                </column>
            @endif

            @if ($recipe['source'])
                @if (Recipes::isSourceUrl($recipe['source']))
                    <pressable
                        ref="recipe-source"
                        @press="openSource"
                        a11y-label="Open source"
                        a11y-hint="Opens the source in the browser"
                        class="{{ Card::classes() }} w-full px-4 py-3"
                    >
                        <row class="w-full items-center gap-3">
                            <icon :ios="Ios::Link" :android="Android::Link" :size="18" class="text-theme-primary" />
                            <column class="flex-1 gap-0.5">
                                <x-ui.text size="sm">Source</x-ui.text>
                                <x-ui.heading>{{ Recipes::shortenedSource($recipe['source']) }}</x-ui.heading>
                            </column>
                            <icon :ios="Ios::ArrowUpRight" :android="Android::OpenInNew" :size="16" class="text-theme-on-surface-variant" />
                        </row>
                    </pressable>
                @else
                    <column ref="recipe-source" class="{{ Card::classes() }} w-full gap-0.5 px-4 py-3">
                        <x-ui.text size="sm">Source</x-ui.text>
                        <x-ui.heading>{{ $recipe['source'] }}</x-ui.heading>
                    </column>
                @endif
            @endif

            @if ($this->ingredients)
                <column class="w-full gap-3">
                    <row class="w-full items-center">
                        <x-ui.heading size="lg" class="flex-1">Ingredients</x-ui.heading>
                        @if ($this->checkedCount)
                            <x-ui.text size="sm">{{ $this->checkedCount }} of {{ count($this->ingredients) }}</x-ui.text>
                        @endif
                    </row>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->ingredients as $index => $ingredient)
                            @php($checked = $this->isChecked($index))
                            <pressable
                                ref="ingredient-{{ $index }}"
                                @press="toggleIngredient({{ $index }})"
                                :a11y-label="($checked ? 'Checked: ' : '').$ingredient"
                                class="w-full px-4 py-3"
                            >
                                <row class="w-full items-center gap-3">
                                    <icon
                                        :ios="$checked ? Ios::CheckmarkCircleFill : Ios::Circle"
                                        :android="$checked ? Android::CheckCircle : Android::RadioButtonUnchecked"
                                        :size="22"
                                        :class="$checked ? 'text-theme-primary' : 'text-theme-outline'"
                                    />
                                    <text :class="$checked ? 'flex-1 text-base text-theme-on-surface-variant line-through' : 'flex-1 text-base text-theme-on-surface'">{{ $ingredient }}</text>
                                </row>
                            </pressable>
                            @unless ($loop->last)
                                <divider class="ml-14" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($this->instructions)
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Instructions</x-ui.heading>
                    <column class="{{ Card::classes() }} w-full gap-4 p-4">
                        @foreach ($this->instructions as $instruction)
                            <row ref="step-{{ $loop->iteration }}" class="w-full items-start gap-3">
                                <column class="h-7 w-7 items-center justify-center rounded-full bg-theme-primary/15">
                                    <text font="accent" class="text-sm text-theme-primary">{{ $loop->iteration }}</text>
                                </column>
                                <text class="flex-1 text-base text-theme-on-surface">{{ $instruction }}</text>
                            </row>
                        @endforeach
                    </column>
                </column>
            @endif

            @if (! $this->ingredients && ! $this->instructions)
                <pressable
                    ref="recipe-add-steps"
                    @navigate('/recipes/'.$recipe['id'].'/edit')
                    a11y-label="Add ingredients and steps"
                    class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6"
                >
                    <icon :ios="Ios::Pencil" :android="Android::Edit" :size="26" class="text-theme-primary" />
                    <x-ui.heading>Add ingredients and steps</x-ui.heading>
                    <x-ui.text size="sm">This recipe doesn’t have any yet.</x-ui.text>
                </pressable>
            @endif

            @if ($recipe['notes'])
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Notes</x-ui.heading>
                    <text ref="recipe-notes" class="{{ Card::classes() }} w-full p-4 text-base text-theme-on-surface">{{ $recipe['notes'] }}</text>
                </column>
            @endif

            @if ($recipe['nutrition'])
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Nutrition</x-ui.heading>
                    <column ref="recipe-nutrition" class="{{ Card::classes() }} w-full gap-1 p-4">
                        @foreach (preg_split('/\R/', $recipe['nutrition']) as $line)
                            <text class="text-base text-theme-on-surface">{{ $line }}</text>
                        @endforeach
                    </column>
                </column>
            @endif

            @foreach (['Remixed from' => $this->parent ? [$this->parent] : [], 'Remixes' => $this->remixes] as $heading => $relatedRecipes)
                @if ($relatedRecipes)
                    <column class="w-full gap-3">
                        <x-ui.heading size="lg">{{ $heading }}</x-ui.heading>
                        <column class="{{ Card::classes() }} w-full">
                            @foreach ($relatedRecipes as $related)
                                <pressable
                                    ref="related-recipe-{{ $related['id'] }}"
                                    @navigate('/recipes/'.$related['id'])
                                    :a11y-label="$related['name']"
                                    class="w-full px-4 py-3"
                                >
                                    <row class="w-full items-center gap-3">
                                        <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary">
                                            <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="16" class="text-theme-on-primary" />
                                        </column>
                                        <x-ui.heading class="flex-1">{{ $related['name'] }}</x-ui.heading>
                                        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                                    </row>
                                </pressable>
                                @unless ($loop->last)
                                    <divider class="ml-16" />
                                @endunless
                            @endforeach
                        </column>
                    </column>
                @endif
            @endforeach
        </column>
    </scroll-view>
@else
    <column ref="recipe-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This recipe could not be found.
        </text>
    </column>
@endif
