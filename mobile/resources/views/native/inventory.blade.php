@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Inventory" back />

<list ref="inventory-list" fill class="bg-theme-background">
    @foreach ($this->items as $item)
        @include('native.inventory-item-row', ['item' => $item, 'from' => null])
    @endforeach
</list>

<native:fab
    ref="inventory-create"
    :ios="Ios::Plus"
    :android="Android::Add"
    a11y-label="New item"
    url="/inventory/create"
/>
