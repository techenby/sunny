@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Cookbook" back />

<list ref="recipe-list" fill separator class="bg-theme-background">
    @foreach ($this->recipes as $recipe)
        <list-item
            :headline="$recipe['name']"
            :supporting="$recipe['summary']"
            :trailingIconIos="Ios::ChevronRight"
            :trailingIconAndroid="Android::ChevronRight"
            @navigate="'/recipes/'.$recipe['id']"
        />
    @endforeach
</list>

<native:fab
    ref="recipes-create"
    :ios="Ios::Plus"
    :android="Android::Add"
    a11y-label="New recipe"
    url="/recipes/create"
/>
