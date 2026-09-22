@use('App\Icons\Android')
@use('App\Icons\Ios')

@php($item = $this->item)

<native:top-bar :title="$item['name'] ?? 'Item'" back />

@if ($item)
    <list ref="item-detail" fill class="bg-theme-background">
        <list-section header="Details">
            <list-item
                headline="Type"
                :trailingText="$item['type']->label()"
                :leadingIconIos="$item['type']->iosIcon()"
                :leadingIconAndroid="$item['type']->androidIcon()"
                :leadingIconColor="$item['type']->iconColor()"
            />
            @if ($this->parent)
                <list-item
                    ref="item-parent"
                    overline="Inside"
                    :headline="$this->parent['name']"
                    :leadingIconIos="$this->parent['type']->iosIcon()"
                    :leadingIconAndroid="$this->parent['type']->androidIcon()"
                    :leadingIconColor="$this->parent['type']->iconColor()"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/inventory/'.$this->parent['id']"
                />
            @endif
        </list-section>

        @if ($item['metadata'])
            <list-section header="Metadata">
                @foreach ($item['metadata'] as $key => $value)
                    <list-item :headline="ucfirst($key)" :trailingText="$value" />
                @endforeach
            </list-section>
        @endif

        @if ($this->children)
            <list-section
                header="Contents"
                :footer="trans_choice(':count item|:count items', count($this->children))"
            >
                @foreach ($this->children as $child)
                    @include('native.inventory-item-row', ['item' => $child])
                @endforeach
            </list-section>
        @endif
    </list>
@else
    <column ref="item-missing" fill center class="bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This item could not be found.
        </text>
    </column>
@endif
