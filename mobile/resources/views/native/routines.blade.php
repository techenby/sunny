@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="All routines" back />

@include('native.search-bottom-bar', [
    'refPrefix' => 'all-routines',
    'placeholder' => 'Search routines',
    'search' => $search,
    'createLabel' => 'New routine',
    'createUrl' => '/routines/create',
])

<scroll-view ref="routine-list" fill class="bg-theme-background">
    <column class="w-full gap-2 px-4 pt-2 pb-8">
        @if ($this->routines)
            <column class="{{ Card::classes() }} w-full">
                @foreach ($this->routines as $routine)
                    @include('native.owned-row', [
                        'ref' => 'all-routines-'.$routine['id'],
                        'url' => '/routines/'.$routine['id'],
                        'headline' => $routine['name'],
                        'supporting' => $routine['summary'],
                        'ios' => $routine['timeOfDay']->iosIcon(),
                        'android' => $routine['timeOfDay']->androidIcon(),
                        'iconBackground' => 'theme-primary',
                        'iconColor' => 'theme-on-primary',
                        'owner' => $routine['owner'],
                        'last' => $loop->last,
                    ])
                @endforeach
            </column>
            <x-ui.text ref="routines-count" size="sm" class="px-1">{{ trans_choice(':count routine|:count routines', count($this->routines)) }}</x-ui.text>
        @else
            <column ref="all-routines-empty" class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6">
                <icon :ios="$search !== '' ? Ios::Magnifyingglass : Ios::ChecklistChecked" :android="$search !== '' ? Android::SearchOff : Android::Checklist" :size="28" class="text-theme-primary" />
                <x-ui.heading class="text-center">{{ $search !== '' ? 'No routines match “'.$search.'”' : 'No routines yet' }}</x-ui.heading>
                <x-ui.text size="sm" class="text-center">{{ $search !== '' ? 'Try a different search.' : 'Tap + to set up a morning, bedtime, or chore routine.' }}</x-ui.text>
            </column>
        @endif
    </column>
</scroll-view>
