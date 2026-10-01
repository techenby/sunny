@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Manage routines" back />

@include('native.search-bottom-bar', [
    'refPrefix' => 'routines',
    'placeholder' => 'Search routines',
    'search' => $search,
    'createLabel' => 'New routine',
    'createUrl' => '/routines/create',
])

<list ref="routine-list" fill separator class="bg-theme-background">
    @if ($this->routines)
        <list-section :footer="trans_choice(':count routine|:count routines', count($this->routines))">
            @foreach ($this->routines as $routine)
                <list-item
                    ref="manage-routine-{{ $routine['id'] }}"
                    :headline="$routine['name']"
                    :supporting="$routine['status'] ? $routine['status'].' · '.$routine['summary'] : $routine['summary']"
                    :leadingIconIos="Ios::ChecklistChecked"
                    :leadingIconAndroid="Android::Checklist"
                    :leadingIconBgColor="theme('primary')"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/routines/'.$routine['id'].'/edit'"
                />
            @endforeach
        </list-section>
    @else
        <list-section>
            <list-item
                ref="routines-empty"
                :headline="$search !== '' ? 'No routines match “'.$search.'”' : 'No routines yet'"
                :supporting="$search !== '' ? 'Try a different search.' : 'Tap + to add your first routine.'"
                :leadingIconIos="$search !== '' ? Ios::Magnifyingglass : Ios::ChecklistChecked"
                :leadingIconAndroid="$search !== '' ? Android::SearchOff : Android::Checklist"
                :leadingIconColor="theme('on-surface-variant')"
            />
        </list-section>
    @endif
</list>
