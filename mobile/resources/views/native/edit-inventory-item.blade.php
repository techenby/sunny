@php($item = $this->item)

<native:top-bar :title="$item ? 'Edit '.$item['name'] : 'Edit item'" back />

@if ($item)
    @include('native.inventory-item-form', [
        'formRef' => 'edit-item',
        'submitLabel' => 'Update item',
        'typeOptions' => $this->typeOptions,
        'parentOptions' => $this->parentOptions,
    ])
@else
    <column ref="edit-item-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This item could not be found.
        </text>
    </column>
@endif
