@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Inventory" display-mode="large" back />

@include('native.search-bottom-bar', [
    'refPrefix' => 'inventory',
    'placeholder' => 'Search all items',
    'search' => $search,
    'createLabel' => 'New item',
    'createUrl' => '/inventory/create',
])

<list ref="inventory-list" fill separator class="bg-theme-background">
    @if ($search !== '')
        <list-section>
            @forelse ($this->searchResults as $result)
                <list-item
                    :headline="$result['name']"
                    :supporting="$result['location'] ? $result['type']->label().' · in '.$result['location'] : $result['type']->label()"
                    :leadingIconIos="$result['type']->iosIcon()"
                    :leadingIconAndroid="$result['type']->androidIcon()"
                    :leadingIconBgColor="$result['type']->iconColor()"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate('/inventory/'.$result['id'])
                />
            @empty
                <list-item
                    ref="inventory-no-matches"
                    :headline="'No items match “'.$search.'”'"
                    supporting="Try a different search."
                    :leadingIconIos="Ios::Magnifyingglass"
                    :leadingIconAndroid="Android::SearchOff"
                    :leadingIconColor="theme('on-surface-variant')"
                />
            @endforelse
        </list-section>
    @else
        <list-section>
            @forelse ($this->items as $item)
                @include('native.inventory-item-row', ['item' => $item, 'from' => null])
            @empty
                <list-item
                    ref="inventory-empty"
                    headline="No items downloaded"
                    supporting="Pull down on the dashboard to refresh."
                    :leadingIconIos="Ios::Archivebox"
                    :leadingIconAndroid="Android::Inventory2"
                    :leadingIconColor="theme('on-surface-variant')"
                />
            @endforelse
        </list-section>
    @endif
</list>
