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
                    @include('native.owned-row', [
                        'ref' => 'checklist-'.$checklist['id'],
                        'url' => '/lists/'.$checklist['id'],
                        'headline' => $checklist['name'],
                        'supporting' => $checklist['summary'],
                        'ios' => $checklist['type']->iosIcon(),
                        'android' => $checklist['type']->androidIcon(),
                        'iconBackground' => $checklist['type']->iconColor(),
                        'owner' => $checklist['owner'],
                        'last' => $loop->last,
                    ])
                @endforeach
            </column>
            <x-ui.text ref="lists-count" size="sm" class="px-1">{{ $this->footer }}</x-ui.text>
        @else
            <column ref="lists-empty" class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6">
                <icon :ios="$search !== '' ? Ios::Magnifyingglass : Ios::ListBullet" :android="$search !== '' ? Android::SearchOff : Android::FormatListBulleted" :size="28" class="text-theme-primary" />
                <x-ui.heading class="text-center">{{ $search !== '' ? 'No lists match “'.$search.'”' : 'No lists yet' }}</x-ui.heading>
                <x-ui.text size="sm" class="text-center">{{ $search !== '' ? 'Try a different search.' : 'Tap + to start a to-do, shopping, or wish list.' }}</x-ui.text>
            </column>
        @endif
    </column>
</scroll-view>
