@use('Native\Mobile\Facades\System')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')
@use('Native\Mobile\Edge\Layouts\Builders\NavAction')

<native:top-bar title="Summary" display-mode="large" :back="false">
    <native:top-bar-action
        ref="preferences"
        id="preferences"
        label="Settings"
        :ios-icon="Ios::Gearshape"
        :android-icon="Android::Settings"
        @navigate('/preferences')
    />
</native:top-bar>

@php
    $addMenu = [
        NavAction::make('add-recipe')->label('New recipe')->icon(ios: Ios::ForkKnife->value, android: Android::Restaurant->value)->url('/recipes/create'),
        NavAction::make('add-item')->label('Add to inventory')->icon(ios: Ios::Shippingbox->value, android: Android::Inventory2->value)->url('/inventory/create'),
        NavAction::make('scan-items')->label('Bulk add')->icon(ios: Ios::CameraViewfinder->value, android: Android::DocumentScanner->value)->url('/inventory/scan'),
    ];
@endphp

<native:bottom-bar>
    <row class="w-full items-center justify-end px-4 pb-2">
        <x-ui.button ref="dashboard-add" variant="glass" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add" :menu="$addMenu" />
    </row>
</native:bottom-bar>

<refreshable ref="dashboard" fill class="bg-theme-background" @refresh="sync">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        @if ($syncError)
            <pressable
                ref="sync-error"
                @tap="sync"
                a11y-label="Download failed. Tap to retry."
                class="w-full rounded-xl bg-theme-destructive/10 px-4 py-3"
            >
                <row class="w-full items-center gap-3">
                    <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="22" class="text-theme-destructive" />
                    <column class="flex-1 gap-0.5">
                        <x-ui.heading class="text-theme-destructive">Download failed</x-ui.heading>
                        <x-ui.text size="sm">{{ $syncError }}</x-ui.text>
                    </column>
                </row>
            </pressable>
        @endif

        @foreach ($this->invitations as $invitation)
            <column ref="dashboard-invitation-{{ $invitation['code'] }}" class="{{ Card::classes() }} w-full gap-3 p-4">
                <row class="w-full items-start gap-3">
                    <x-ui.avatar circle color="red" :initials="$invitation['initials']" />
                    <column class="flex-1 gap-0.5">
                        <x-ui.heading>{{ $invitation['inviter'] ? $invitation['inviter'].' invited you to join '.$invitation['team'] : 'You’re invited to join '.$invitation['team'] }}</x-ui.heading>
                        <x-ui.text size="sm">{{ collect([$invitation['sent'] ? 'Sent '.$invitation['sent'] : null, $invitation['expires']])->filter()->implode(' · ') }}</x-ui.text>
                    </column>
                </row>
                <x-ui.button
                    ref="dashboard-accept-{{ $invitation['code'] }}"
                    class="w-full"
                    tap="acceptInvitation('{{ $invitation['code'] }}')"
                >
                    Join {{ $invitation['team'] }}
                </x-ui.button>
                <x-ui.button
                    ref="dashboard-decline-{{ $invitation['code'] }}"
                    variant="secondary"
                    class="w-full"
                    tap="declineInvitation('{{ $invitation['code'] }}')"
                >
                    Decline
                </x-ui.button>
            </column>
        @endforeach

        @unless ($this->teamOptions)
            <column ref="no-teams" class="{{ Card::classes() }} w-full gap-0.5 px-4 py-3">
                <x-ui.heading>No teams available</x-ui.heading>
                <x-ui.text size="sm">Pull down to download your teams.</x-ui.text>
            </column>
        @endunless

        @if ($this->teamOptions)
            @php($comingUp = $this->comingUp)
            <column ref="coming-up" class="w-full gap-3">
                <pressable ref="open-calendar" a11y-label="Coming up, open Calendar" class="min-h-12 w-full justify-center" @navigate('/calendar')>
                    <row class="w-full items-center gap-3">
                        <x-ui.heading size="lg" class="flex-1">Coming up</x-ui.heading>
                        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-primary-text" />
                    </row>
                </pressable>

                @if ($comingUp && $calendarStatus !== '' && $this->calendarUpdatedAt)
                    <row ref="coming-up-status" class="w-full items-start gap-2">
                        <icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="14" class="mt-0.5 text-theme-on-surface-variant" />
                        <x-ui.text size="sm" class="flex-1">{{ $calendarStatus === 'offline' ? 'You’re offline.' : 'The server couldn’t refresh events.' }} Last updated {{ $this->calendarUpdatedAt }}.</x-ui.text>
                    </row>
                @endif

                <column class="{{ Card::classes() }} w-full py-1">
                    @if (($this->calendarFeeds['feeds'] ?? null) === [])
                        <pressable ref="coming-up-no-calendars" a11y-label="No calendars yet. Connect one to see what’s coming up." class="w-full px-4 py-3" @navigate('/calendar')>
                            <row class="w-full items-center gap-3">
                                <icon :ios="Ios::Calendar" :android="Android::CalendarMonth" :size="18" class="text-theme-primary" />
                                <x-ui.text size="sm" class="flex-1">No calendars yet. Connect one to see what’s coming up.</x-ui.text>
                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                            </row>
                        </pressable>
                    @elseif ($comingUp)
                        @foreach ($this->calendarSnapshot['warnings'] as $warning)
                            <pressable ref="coming-up-warning-{{ $warning['feed_id'] }}" :a11y-label="$warning['feed_name'].' isn’t showing. Fix it in Calendars.'" class="w-full px-4 py-2" @navigate('/calendar/feeds')>
                                <row class="w-full items-center gap-2">
                                    <icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::Warning" :size="14" class="text-theme-destructive" />
                                    <text font="medium" class="flex-1 text-sm text-theme-destructive">{{ $warning['feed_name'] }} isn’t showing</text>
                                    <text font="semibold" class="text-sm text-theme-primary-text">Fix</text>
                                </row>
                            </pressable>
                        @endforeach
                        @foreach ($comingUp as $day)
                            @unless ($loop->first)
                                <divider class="mx-4 my-1" />
                            @endunless
                            <row class="w-full items-center px-4 pt-2 pb-1">
                                <text font="accent" class="flex-1 text-xs text-theme-on-surface">{{ $day['name'] }}</text>
                                <text font="medium" class="text-xs text-theme-on-surface-variant">{{ $day['date'] }}</text>
                            </row>
                            @forelse ($day['events'] as $event)
                                @include('native.calendar-event', ['event' => $event, 'ref' => 'coming-up-'.$loop->parent->index.'-'.$loop->index])
                            @empty
                                <row ref="coming-up-{{ $loop->index }}-empty" class="w-full items-center gap-2 px-4 pt-1 pb-3">
                                    <icon :ios="Ios::SunMax" :android="Android::WbSunny" :size="16" class="text-theme-primary" />
                                    <x-ui.text size="sm">Nothing scheduled</x-ui.text>
                                </row>
                            @endforelse
                        @endforeach
                    @else
                        <row ref="coming-up-unavailable" class="w-full items-center gap-3 px-4 py-3">
                            @if ($this->calendarIsLoading)
                                <activity-indicator />
                                <x-ui.text size="sm">Loading events…</x-ui.text>
                            @elseif ($calendarStatus === 'offline')
                                <icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="16" class="text-theme-on-surface-variant" />
                                <x-ui.text size="sm" class="flex-1">You’re offline. Events show up here once the server can be reached.</x-ui.text>
                            @else
                                <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="16" class="text-theme-on-surface-variant" />
                                <x-ui.text size="sm" class="flex-1">The server couldn’t load events. Pull down to try again.</x-ui.text>
                            @endif
                        </row>
                    @endif
                </column>
            </column>
        @endif

        <column class="w-full gap-3">
            <x-ui.heading size="lg">Recent recipes</x-ui.heading>
            @if ($this->recentRecipes)
                {{-- iOS insets the carousel's content by 16pt, so let it bleed to the screen edges to line up with the heading. --}}
                <carousel ref="recent-recipes" variant="uncontained" :item-width="180" :item-spacing="12" :class="System::isIos() ? '-mx-4' : null">
                    @foreach ($this->recentRecipes as $recipe)
                        <pressable
                            ref="dashboard-recipes-{{ $recipe['id'] }}"
                            @navigate($recipe['url'])
                            :a11y-label="$recipe['name']"
                            class="{{ Card::classes() }} min-h-[210] w-full"
                        >
                            <stack class="w-full h-[130]">
                                <column class="w-full h-full items-center justify-center bg-theme-primary/15">
                                    <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="32" class="text-theme-primary" />
                                </column>
                                @if ($recipe['photo'])
                                    <image :src="$recipe['photo']" :alt="'Photo of '.$recipe['name']" class="w-full h-full object-cover" />
                                @endif
                            </stack>
                            <column class="w-full gap-0.5 px-3 py-2">
                                <x-ui.heading max-lines="2">{{ $recipe['name'] }}</x-ui.heading>
                                <x-ui.text size="sm" max-lines="2">{{ $recipe['supporting'] }}</x-ui.text>
                            </column>
                        </pressable>
                    @endforeach
                </carousel>
            @else
                <pressable
                    ref="recipes-empty"
                    @navigate('/recipes/create')
                    a11y-label="Create your first recipe"
                    class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6"
                >
                    <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="28" class="text-theme-primary" />
                    <x-ui.heading>Create your first recipe</x-ui.heading>
                    <x-ui.text size="sm">Recipes you create or update will show up here.</x-ui.text>
                </pressable>
            @endif
        </column>

        <column class="w-full gap-3">
            <x-ui.heading size="lg">Recent inventory</x-ui.heading>
            @if ($this->recentItems)
                <column class="{{ Card::classes() }} w-full">
                    @foreach ($this->recentItems as $item)
                        <pressable
                            ref="dashboard-inventory-{{ $item['id'] }}"
                            @navigate($item['url'])
                            :a11y-label="$item['name']"
                            :press-opacity="0.7"
                            class="w-full px-4 py-3"
                        >
                            <row class="w-full items-start gap-4">
                                <x-ui.avatar
                                    :src="$item['photo']"
                                    :alt="'Photo of '.$item['name']"
                                    :color="$item['type']->color()"
                                    :ios="$item['type']->iosIcon()"
                                    :android="$item['type']->androidIcon()"
                                />
                                <column class="flex-1 gap-0.5">
                                    <x-ui.heading max-lines="1">{{ $item['name'] }}</x-ui.heading>
                                    <x-ui.text size="sm" max-lines="1">{{ $item['supporting'] }}</x-ui.text>
                                </column>
                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="self-center text-theme-on-surface-variant" />
                            </row>
                        </pressable>
                        @unless ($loop->last)
                            <divider class="ml-[72]" />
                        @endunless
                    @endforeach
                </column>
            @else
                <pressable
                    ref="items-empty"
                    @navigate('/inventory/create')
                    a11y-label="Add to inventory"
                    class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6"
                >
                    <icon :ios="Ios::Archivebox" :android="Android::Inventory2" :size="28" class="text-theme-accent" />
                    <x-ui.heading>Add to inventory</x-ui.heading>
                    <x-ui.text size="sm">Things you add or update will show up here.</x-ui.text>
                </pressable>
            @endif
        </column>
    </column>
</refreshable>
