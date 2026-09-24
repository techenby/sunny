@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Inventory" back />

<list ref="inventory-list" fill class="bg-theme-background">
    <list-section :footer="$this->items ? null : 'No items downloaded. Pull down on the dashboard to refresh.'">
        @foreach ($this->items as $item)
            @include('native.inventory-item-row', ['item' => $item, 'from' => null])
        @endforeach
    </list-section>
</list>

<native:fab
    ref="inventory-create"
    :ios="Ios::Plus"
    :android="Android::Add"
    a11y-label="New item"
    url="/inventory/create"
/>
