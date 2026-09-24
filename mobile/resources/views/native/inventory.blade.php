@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Inventory" display-mode="large" back search-placeholder="Search all items" search-on-query="updateSearch">
    <native:top-bar-action
        id="inventory-create"
        label="New item"
        :ios-icon="Ios::Plus"
        :android-icon="Android::Add"
        url="/inventory/create"
    />
</native:top-bar>

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
