<native:top-bar title="Inventory" back />

<list ref="inventory-list" fill class="bg-theme-background">
    @foreach ($this->locations as $location)
        <list-section
            :header="$location['location']"
            :footer="trans_choice(':count item|:count items', count($location['items']))"
        >
            @foreach ($location['items'] as $item)
                <list-item
                    :headline="$item['name']"
                    :supporting="$item['spot']"
                    :leadingIconIos="$location['ios']"
                    :leadingIconAndroid="$location['android']"
                    :trailingText="(string) $item['quantity']"
                    @navigate="'/inventory/'.$item['id']"
                />
            @endforeach
        </list-section>
    @endforeach
</list>
