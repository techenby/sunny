@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Recipes')

@php($recipe = $this->recipe)

<native:top-bar :title="$recipe['name'] ?? 'Recipe'" back>
    @if ($recipe)
        <native:top-bar-action
            ref="edit-recipe"
            id="edit-recipe"
            label="Edit"
            :ios-icon="Ios::Pencil"
            :android-icon="Android::Edit"
            @navigate('/recipes/'.$recipe['id'].'/edit')
        />
    @endif
</native:top-bar>

@if ($recipe)
    <scroll-view ref="recipe-detail" fill class="bg-theme-background">
        <column class="w-full gap-6 px-4 pt-2 pb-8">
            <column class="w-full gap-3">
                @if ($recipe['photo_url'] ?? null)
                    <image
                        ref="recipe-photo"
                        :src="$recipe['photo_url']"
                        :alt="'Photo of '.$recipe['name']"
                        class="w-full h-[240] rounded-xl object-cover"
                    />
                @else
                    <column class="w-full h-[160] items-center justify-center rounded-xl bg-theme-primary/15">
                        <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="44" class="text-theme-primary" />
                    </column>
                @endif

                <text ref="recipe-name" font="accent" class="text-3xl text-theme-on-background">{{ $recipe['name'] }}</text>

                @if ($recipe['description'])
                    <text ref="recipe-description" class="text-base text-theme-on-surface-variant">{{ $recipe['description'] }}</text>
                @endif

                @if ($recipe['tags'])
                    <row ref="recipe-tags" class="w-full flex-wrap gap-2">
                        @foreach ($recipe['tags'] as $tag)
                            <text font="semibold" class="rounded-full bg-theme-primary/15 px-3 py-1 text-sm text-theme-primary">{{ $tag }}</text>
                        @endforeach
                    </row>
                @endif
            </column>

            @if ($this->details)
                <column ref="recipe-facts" class="w-full gap-3">
                    @foreach (array_chunk($this->details, 2, true) as $pair)
                        <row class="w-full gap-3">
                            @foreach ($pair as $label => $value)
                                <column class="flex-1 gap-0.5 rounded-xl bg-theme-surface p-4 shadow-sm">
                                    <text class="text-sm text-theme-on-surface-variant">{{ $label }}</text>
                                    <text font="semibold" class="text-base text-theme-on-surface">{{ $value }}</text>
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
                        class="w-full rounded-xl bg-theme-surface px-4 py-3 shadow-sm"
                    >
                        <row class="w-full items-center gap-3">
                            <icon :ios="Ios::Link" :android="Android::Link" :size="18" class="text-theme-primary" />
                            <column class="flex-1 gap-0.5">
                                <text class="text-sm text-theme-on-surface-variant">Source</text>
                                <text font="semibold" class="text-base text-theme-on-surface">{{ Recipes::shortenedSource($recipe['source']) }}</text>
                            </column>
                            <icon :ios="Ios::ArrowUpRight" :android="Android::OpenInNew" :size="16" class="text-theme-on-surface-variant" />
                        </row>
                    </pressable>
                @else
                    <column ref="recipe-source" class="w-full gap-0.5 rounded-xl bg-theme-surface px-4 py-3 shadow-sm">
                        <text class="text-sm text-theme-on-surface-variant">Source</text>
                        <text font="semibold" class="text-base text-theme-on-surface">{{ $recipe['source'] }}</text>
                    </column>
                @endif
            @endif

            @if ($this->ingredients)
                <column class="w-full gap-3">
                    <row class="w-full items-center">
                        <text font="semibold" class="flex-1 text-xl text-theme-on-background">Ingredients</text>
                        @if ($checkedIngredients)
                            <text class="text-sm text-theme-on-surface-variant">{{ count($checkedIngredients) }} of {{ count($this->ingredients) }}</text>
                        @endif
                    </row>
                    <column class="w-full rounded-xl bg-theme-surface">
                        @foreach ($this->ingredients as $index => $ingredient)
                            @php($checked = in_array($index, $checkedIngredients, true))
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
                    <text font="semibold" class="text-xl text-theme-on-background">Instructions</text>
                    <column class="w-full gap-4 rounded-xl bg-theme-surface p-4">
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
                    class="w-full items-center gap-2 rounded-xl bg-theme-surface px-4 py-6"
                >
                    <icon :ios="Ios::Pencil" :android="Android::Edit" :size="26" class="text-theme-primary" />
                    <text font="semibold" class="text-base text-theme-on-surface">Add ingredients and steps</text>
                    <text class="text-sm text-theme-on-surface-variant">This recipe doesn’t have any yet.</text>
                </pressable>
            @endif

            @if ($recipe['notes'])
                <column class="w-full gap-3">
                    <text font="semibold" class="text-xl text-theme-on-background">Notes</text>
                    <text ref="recipe-notes" class="w-full rounded-xl bg-theme-surface p-4 text-base text-theme-on-surface">{{ $recipe['notes'] }}</text>
                </column>
            @endif

            @if ($recipe['nutrition'])
                <column class="w-full gap-3">
                    <text font="semibold" class="text-xl text-theme-on-background">Nutrition</text>
                    <column ref="recipe-nutrition" class="w-full gap-1 rounded-xl bg-theme-surface p-4">
                        @foreach (preg_split('/\R/', $recipe['nutrition']) as $line)
                            <text class="text-base text-theme-on-surface">{{ $line }}</text>
                        @endforeach
                    </column>
                </column>
            @endif

            @foreach (['Remixed from' => $this->parent ? [$this->parent] : [], 'Remixes' => $this->remixes] as $heading => $relatedRecipes)
                @if ($relatedRecipes)
                    <column class="w-full gap-3">
                        <text font="semibold" class="text-xl text-theme-on-background">{{ $heading }}</text>
                        <column class="w-full rounded-xl bg-theme-surface">
                            @foreach ($relatedRecipes as $related)
                                <pressable
                                    ref="related-recipe-{{ $related['id'] }}"
                                    @navigate('/recipes/'.$related['id'])
                                    :a11y-label="$related['name']"
                                    class="w-full px-4 py-3"
                                >
                                    <row class="w-full items-center gap-3">
                                        <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                                            <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="16" class="text-theme-primary" />
                                        </column>
                                        <text font="semibold" class="flex-1 text-base text-theme-on-surface">{{ $related['name'] }}</text>
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
