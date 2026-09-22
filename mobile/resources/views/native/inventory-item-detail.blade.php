@php($item = $this->item)

<native:top-bar :title="$item['name'] ?? 'Item'" back />

@if ($item)
    <list ref="item-detail" fill class="bg-theme-background">
        <list-section header="Details">
            <list-item headline="Location" :trailingText="$item['location']" />
            <list-item headline="Spot" :trailingText="$item['spot']" />
            <list-item headline="Quantity" :trailingText="(string) $item['quantity']" />
        </list-section>

        <list-section header="Notes">
            <list-item :headline="$item['notes']" />
        </list-section>
    </list>
@else
    <column ref="item-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This item could not be found.
        </text>
    </column>
@endif
