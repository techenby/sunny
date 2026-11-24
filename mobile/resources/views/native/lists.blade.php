@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Lists" display-mode="large" :back="false" />

@include('native.search-bottom-bar', [
    'refPrefix' => 'lists',
    'placeholder' => 'Search lists',
    'search' => $search,
    'createLabel' => 'New list',
    'createUrl' => '/lists/create',
])

<scroll-view ref="checklist-list" fill class="bg-theme-background">
    <column class="w-full gap-2 px-4 pt-2 pb-8">
        @if ($this->lists)
            <column class="{{ Card::classes() }} w-full">
                @foreach ($this->lists as $checklist)
                    <x-ui.row
                        :ref="'checklist-'.$checklist['id']"
                        :url="'/lists/'.$checklist['id']"
                        :headline="$checklist['name']"
                        :supporting="$checklist['summary']"
                        :ios="$checklist['type']->iosIcon()"
                        :android="$checklist['type']->androidIcon()"
                        :icon-background="$checklist['type']->iconColor()"
                        :owner="$checklist['owner']"
                        trailing="chevron"
                        :last="$loop->last"
                    />
                @endforeach
            </column>
            <x-ui.text ref="lists-count" size="sm" class="px-1">{{ $this->footer }}</x-ui.text>
        @else
            <x-ui.empty-state
                ref="lists-empty"
                :ios="$search !== '' ? Ios::Magnifyingglass : Ios::ListBullet"
                :android="$search !== '' ? Android::SearchOff : Android::FormatListBulleted"
                :heading="$search !== '' ? 'No lists match “'.$search.'”' : 'No lists yet'"
                :text="$search !== '' ? 'Try a different search.' : 'Tap + to start a to-do, shopping, or wish list.'"
            />
        @endif
    </column>
</scroll-view>
