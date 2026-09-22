@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Recipes" back />

<list ref="recipe-list" fill class="bg-theme-background">
    @foreach ($this->courses as $course)
        <list-section
            :header="$course['course']"
            :footer="trans_choice(':count recipe|:count recipes', count($course['recipes']))"
        >
            @foreach ($course['recipes'] as $recipe)
                <list-item
                    :headline="$recipe['name']"
                    :supporting="'Serves '.$recipe['servings'].' · '.$recipe['minutes'].' min'"
                    :leadingIconIos="$course['ios']"
                    :leadingIconAndroid="$course['android']"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/recipes/'.$recipe['id']"
                />
            @endforeach
        </list-section>
    @endforeach
</list>
