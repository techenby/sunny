@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Recipes" display-mode="large" :back="false" />

@include('native.search-bottom-bar', [
    'refPrefix' => 'recipes',
    'placeholder' => 'Search recipes',
    'search' => $search,
    'createLabel' => 'New recipe',
    'createUrl' => '/recipes/create',
])

<list ref="recipe-list" fill class="bg-theme-background">
    @if ($this->recipes)
        <list-section :footer="trans_choice(':count recipe|:count recipes', count($this->recipes))">
            @foreach ($this->recipes as $recipe)
                @include('native.owned-row', [
                    'ref' => 'recipe-'.$recipe['id'],
                    'url' => '/recipes/'.$recipe['id'],
                    'headline' => $recipe['name'],
                    'supporting' => $recipe['summary'],
                    'ios' => Ios::ForkKnife,
                    'android' => Android::Restaurant,
                    'tile' => true,
                    'photo' => $recipe['photo'],
                    'owner' => null,
                    'last' => $loop->last,
                ])
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
