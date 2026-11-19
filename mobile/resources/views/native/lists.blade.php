@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Lists" display-mode="large" :back="false" />

@include('native.search-bottom-bar', [
    'refPrefix' => 'lists',
    'placeholder' => 'Search lists',
    'search' => $search,
    'createLabel' => 'New list',
    'createUrl' => '/lists/create',
])

<list ref="checklist-list" fill class="bg-theme-background">
    @if ($this->lists)
        <list-section :footer="$this->footer">
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
        </list-section>
    @else
        <list-section>
            <list-item
                ref="lists-empty"
                :headline="$search !== '' ? 'No lists match “'.$search.'”' : 'No lists yet'"
                :supporting="$search !== '' ? 'Try a different search.' : 'Tap + to start a to-do, shopping, or wish list.'"
                :leadingIconIos="$search !== '' ? Ios::Magnifyingglass : Ios::ListBullet"
                :leadingIconAndroid="$search !== '' ? Android::SearchOff : Android::FormatListBulleted"
                :leadingIconColor="theme('on-surface-variant')"
            />
        </list-section>
    @endif
</list>
