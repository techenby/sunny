<native:top-bar title="Inventory" back />

<list ref="inventory-list" fill class="bg-theme-background">
    @foreach ($this->items as $item)
        @include('native.inventory-item-row', ['item' => $item, 'from' => null])
    @endforeach
</list>
