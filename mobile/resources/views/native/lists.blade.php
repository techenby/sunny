@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Lists" display-mode="large" :back="false" />

@include('native.search-bottom-bar', [
    'refPrefix' => 'lists',
    'placeholder' => 'Search lists',
    'search' => $search,
    'createLabel' => 'New list',
    'createUrl' => '/lists/create',
])

<list ref="checklist-list" fill separator class="bg-theme-background">
    @if ($this->lists)
        <list-section :footer="trans_choice(':count list|:count lists', count($this->lists))">
            @foreach ($this->lists as $checklist)
                <list-item
                    :headline="$checklist['name']"
                    :supporting="$checklist['summary']"
                    :leadingIconIos="$checklist['type']->iosIcon()"
                    :leadingIconAndroid="$checklist['type']->androidIcon()"
                    :leadingIconBgColor="$checklist['type']->iconColor()"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/lists/'.$checklist['id']"
                />
            @endforeach
        </list-section>
    @else
        <list-section>
            <list-item
                ref="lists-empty"
                :headline="$search !== '' ? 'No lists match “'.$search.'”' : 'No lists yet'"
                :supporting="$search !== '' ? 'Try a different search.' : 'Tap + to start a to-do, shopping, or wish list.'"
                :leadingIconIos="$search !== '' ? Ios::Magnifyingglass : Ios::ListBullet"
                :leadingIconAndroid="$search !== '' ? Android::SearchOff : Android::FormatListBulleted"
                :leadingIconColor="theme('on-surface-variant')"
            />
        </list-section>
    @endif
</list>
