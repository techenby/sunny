@php($recipe = $this->recipe)

<native:top-bar :title="$recipe['name'] ?? 'Recipe'" back />

@if ($recipe)
    <list ref="recipe-detail" fill class="bg-theme-background">
        <list-section header="Details">
            <list-item headline="Course" :trailingText="$recipe['course']" />
            <list-item headline="Serves" :trailingText="(string) $recipe['servings']" />
            <list-item headline="Prep time" :trailingText="$recipe['prepMinutes'].' min'" />
            <list-item headline="Cook time" :trailingText="$recipe['cookMinutes'].' min'" />
        </list-section>

        <list-section header="Ingredients">
            @foreach ($recipe['ingredients'] as $ingredient)
                <list-item :headline="$ingredient" />
            @endforeach
        </list-section>

        <list-section header="Steps">
            @foreach ($recipe['steps'] as $step)
                <list-item :overline="'Step '.$loop->iteration" :headline="$step" />
            @endforeach
        </list-section>
    </list>
@else
    <column ref="recipe-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This recipe could not be found.
        </text>
    </column>
@endif
