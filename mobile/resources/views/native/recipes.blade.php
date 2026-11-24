@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Recipes" display-mode="large" :back="false" />

@include('native.search-bottom-bar', [
    'refPrefix' => 'recipes',
    'placeholder' => 'Search recipes',
    'search' => $search,
    'createLabel' => 'New recipe',
    'createUrl' => '/recipes/create',
])

<scroll-view ref="recipe-list" fill class="bg-theme-background">
    <column class="w-full gap-2 px-4 pt-2 pb-8">
        @if ($this->recipes)
            <column class="{{ Card::classes() }} w-full">
                @foreach ($this->recipes as $recipe)
                    <x-ui.row
                        :ref="'recipe-'.$recipe['id']"
                        :url="'/recipes/'.$recipe['id']"
                        :headline="$recipe['name']"
                        :supporting="$recipe['summary']"
                        :ios="Ios::ForkKnife"
                        :android="Android::Restaurant"
                        tile
                        :src="$recipe['photo']"
                        :alt="'Photo of '.$recipe['name']"
                        trailing="chevron"
                        :last="$loop->last"
                    />
                @endforeach
            </column>
            <x-ui.text ref="recipes-count" size="sm" class="px-1">{{ trans_choice(':count recipe|:count recipes', count($this->recipes)) }}</x-ui.text>
        @else
            <x-ui.empty-state
                ref="recipes-empty"
                :ios="$search !== '' ? Ios::Magnifyingglass : Ios::ForkKnife"
                :android="$search !== '' ? Android::SearchOff : Android::Restaurant"
                :heading="$search !== '' ? 'No recipes match “'.$search.'”' : 'No recipes downloaded'"
                :text="$search !== '' ? 'Try a different search.' : 'Pull down on Summary to refresh.'"
            />
        @endif
    </column>
</scroll-view>
