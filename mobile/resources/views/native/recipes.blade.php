@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Recipes" display-mode="large" back />

@include('native.search-bottom-bar', [
    'refPrefix' => 'recipes',
    'placeholder' => 'Search recipes',
    'search' => $search,
    'createLabel' => 'New recipe',
    'createUrl' => '/recipes/create',
])

<list ref="recipe-list" fill separator class="bg-theme-background">
    @if ($this->recipes)
        <list-section :footer="trans_choice(':count recipe|:count recipes', count($this->recipes))">
            @foreach ($this->recipes as $recipe)
                <list-item
                    :headline="$recipe['name']"
                    :supporting="$recipe['summary']"
                    :leadingImage="$recipe['photo']"
                    :leadingIconIos="$recipe['photo'] ? null : Ios::ForkKnife"
                    :leadingIconAndroid="$recipe['photo'] ? null : Android::Restaurant"
                    :leadingIconBgColor="theme('primary')"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/recipes/'.$recipe['id']"
                />
            @endforeach
        </list-section>
    @else
        <list-section>
            <list-item
                ref="recipes-empty"
                :headline="$search !== '' ? 'No recipes match “'.$search.'”' : 'No recipes downloaded'"
                :supporting="$search !== '' ? 'Try a different search.' : 'Pull down on the dashboard to refresh.'"
                :leadingIconIos="$search !== '' ? Ios::Magnifyingglass : Ios::ForkKnife"
                :leadingIconAndroid="$search !== '' ? Android::SearchOff : Android::Restaurant"
                :leadingIconColor="theme('on-surface-variant')"
            />
        </list-section>
    @endif
</list>
