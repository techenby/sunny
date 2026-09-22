@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Recipes')

@php($recipe = $this->recipe)

<native:top-bar :title="$recipe['name'] ?? 'Recipe'" back />

@if ($recipe)
    <list ref="recipe-detail" fill class="bg-theme-background">
        @if ($recipe['description'] || $recipe['tags'])
            <list-section header="Description">
                <list-item
                    ref="recipe-description"
                    :headline="$recipe['description'] ?? implode(', ', $recipe['tags'])"
                    :supporting="$recipe['description'] && $recipe['tags'] ? implode(', ', $recipe['tags']) : null"
                />
            </list-section>
        @endif

        @if ($recipe['source'] || $this->details)
            <list-section header="Details">
                @if ($recipe['source'])
                    @if (Recipes::isSourceUrl($recipe['source']))
                        <list-item
                            ref="recipe-source"
                            headline="Source"
                            :supporting="Recipes::shortenedSource($recipe['source'])"
                            :trailingIconIos="Ios::ArrowUpRight"
                            :trailingIconAndroid="Android::OpenInNew"
                            a11y-hint="Opens the source in the browser"
                            @press="openSource"
                        />
                    @else
                        <list-item ref="recipe-source" headline="Source" :trailingText="$recipe['source']" />
                    @endif
                @endif
                @foreach ($this->details as $label => $value)
                    <list-item :headline="$label" :trailingText="$value" />
                @endforeach
            </list-section>
        @endif

        @if ($this->ingredients)
            <list-section header="Ingredients">
                @foreach ($this->ingredients as $ingredient)
                    <list-item :headline="$ingredient" />
                @endforeach
            </list-section>
        @endif

        @if ($this->instructions)
            <list-section header="Instructions">
                @foreach ($this->instructions as $instruction)
                    <list-item :overline="'Step '.$loop->iteration" :headline="$instruction" />
                @endforeach
            </list-section>
        @endif

        @if ($recipe['notes'])
            <list-section header="Notes">
                <list-item :headline="$recipe['notes']" />
            </list-section>
        @endif

        @if ($recipe['nutrition'])
            <list-section header="Nutrition">
                @foreach (preg_split('/\R/', $recipe['nutrition']) as $line)
                    <list-item :headline="$line" />
                @endforeach
            </list-section>
        @endif

        @if ($this->parent)
            <list-section header="Remixed from">
                <list-item
                    :headline="$this->parent['name']"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/recipes/'.$this->parent['id']"
                />
            </list-section>
        @endif

        @if ($this->remixes)
            <list-section header="Remixes">
                @foreach ($this->remixes as $remix)
                    <list-item
                        :headline="$remix['name']"
                        :trailingIconIos="Ios::ChevronRight"
                        :trailingIconAndroid="Android::ChevronRight"
                        @navigate="'/recipes/'.$remix['id']"
                    />
                @endforeach
            </list-section>
        @endif
    </list>
@else
    <column ref="recipe-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This recipe could not be found.
        </text>
    </column>
@endif
