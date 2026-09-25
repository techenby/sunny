@php($item = $this->item)

<native:top-bar :title="$item ? 'Edit '.$item['name'] : 'Edit item'" back />

@if ($item)
    @include('native.inventory-item-form', [
        'formRef' => 'edit-item',
        'submitLabel' => 'Update item',
        'typeOptions' => $this->typeOptions,
        'selectedParent' => $this->selectedParent,
        'browsedParent' => $this->browsedParent,
        'parentPickerRows' => $this->parentPickerRows,
    ])
@else
    <column ref="edit-item-missing" fill center class="bg-theme-background ios:bg-theme-grouped-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This item could not be found.
        </text>
    </column>
@endif
