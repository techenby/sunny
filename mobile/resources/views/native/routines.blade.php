@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="All routines" back />

@include('native.search-bottom-bar', [
    'refPrefix' => 'all-routines',
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
                    :headline="$routine['name']"
                    :supporting="$routine['summary']"
                    :leadingIconIos="$routine['timeOfDay']->iosIcon()"
                    :leadingIconAndroid="$routine['timeOfDay']->androidIcon()"
                    :leadingIconColor="theme('primary')"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/routines/'.$routine['id']"
                />
            @endforeach
        </list-section>
    @else
        <list-section>
            <list-item
                ref="all-routines-empty"
                :headline="$search !== '' ? 'No routines match “'.$search.'”' : 'No routines yet'"
                :supporting="$search !== '' ? 'Try a different search.' : 'Tap + to set up a morning, bedtime, or chore routine.'"
                :leadingIconIos="$search !== '' ? Ios::Magnifyingglass : Ios::ChecklistChecked"
                :leadingIconAndroid="$search !== '' ? Android::SearchOff : Android::Checklist"
                :leadingIconColor="theme('on-surface-variant')"
            />
        </list-section>
    @endif
</list>
