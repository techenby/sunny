@use('App\Enums\TeamRole')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Preferences')
@use('App\NativeComponents\TeamSettings')
@use('App\Ui\Card')

@php
    $team = $this->team();
    $members = $this->members();
    $invitations = $this->invitations();
    $role = $this->role();
@endphp

<native:top-bar :title="$team?->name ?? 'Team'" back />

@if ($team === null)
    <column ref="team-settings-empty" fill center class="gap-2 bg-theme-background px-6">
        <x-ui.heading size="lg" class="text-center">No team yet</x-ui.heading>
        <x-ui.text class="text-center">Pull down on Summary to download your teams.</x-ui.text>
    </column>
@else
    <list ref="team-settings" fill class="bg-theme-background">
        @if ($offline)
            <list-section>
                <list-item
                    ref="team-offline"
                    headline="You’re offline"
                    :supporting="'This is '.$team->name.($this->syncedAt() ? ' as of '.$this->syncedAt() : '').'. Changing the team needs a connection, so it’s look-only until you’re back.'"
                    :leadingIconIos="Ios::WifiSlash"
                    :leadingIconAndroid="Android::WifiOff"
                    :leadingIconColor="theme('primary-text')"
                    :headlineColor="theme('primary-text')"
                    :trailingIconIos="Ios::ArrowClockwise"
                    :trailingIconAndroid="Android::Refresh"
                    a11y-hint="Checks the connection again"
                    @tap="retry"
                />
            </list-section>
        @endif

        <list-section header="Team">
            <list-item
                ref="team-name"
                :headline="$team->name"
                supporting="Name"
                :leadingMonogram="mb_strtoupper(mb_substr($team->name, 0, 1))"
                :leadingMonogramColor="theme('primary')"
                :trailingText="$role?->canUpdateTeam() ? 'Rename' : null"
                :trailingTextColor="$this->canEdit() ? theme('primary-text') : theme('on-surface-variant')"
                :disabled="$role?->canUpdateTeam() && ! $this->canEdit()"
                a11y-hint="{{ $this->canEdit() ? 'Renames the team' : '' }}"
                @tap="openRename"
            />
        </list-section>

        <list-section header="Household" footer="Routines reset at midnight in this timezone, so everyone’s day starts together.">
            <list-item
                ref="team-timezone"
                headline="Timezone"
                :supporting="$team->timezone ?? 'Not set'"
                :leadingIconIos="Ios::Globe"
                :leadingIconAndroid="Android::Public"
                :trailingIconIos="$role?->canUpdateTeam() ? Ios::ChevronRight : null"
                :trailingIconAndroid="$role?->canUpdateTeam() ? Android::ChevronRight : null"
                :disabled="$role?->canUpdateTeam() && ! $this->canEdit()"
                @tap="openTimezone"
            />
            <list-item
                ref="team-week-start"
                headline="Week starts on"
                :supporting="$this->weekStart()"
                :leadingIconIos="Ios::Calendar"
                :leadingIconAndroid="Android::CalendarMonth"
                :trailingIconIos="$role?->canUpdateTeam() ? Ios::ChevronRight : null"
                :trailingIconAndroid="$role?->canUpdateTeam() ? Android::ChevronRight : null"
                :disabled="$role?->canUpdateTeam() && ! $this->canEdit()"
                @tap="openWeekStart"
            />
        </list-section>

        <list-section :header="'Members'.($members ? ' · '.count($members) : '')" :footer="$this->membersFooter()">
            @forelse ($members as $member)
                <list-item
                    ref="member-{{ $member['id'] }}"
                    :headline="$member['name']"
                    :supporting="$member['role']->label().($member['you'] ? ' · You' : '')"
                    :leadingMonogram="$member['initials']"
                    :leadingMonogramColor="theme('primary')"
                    :trailingIconIos="$member['tappable'] ? Ios::ChevronRight : null"
                    :trailingIconAndroid="$member['tappable'] ? Android::ChevronRight : null"
                    :disabled="$member['tappable'] && $offline"
                    a11y-hint="{{ $member['tappable'] ? 'Changes their role or removes them' : '' }}"
                    @tap="openMember({{ $member['id'] }})"
                />
            @empty
                <list-item ref="no-members" headline="No members downloaded yet" />
            @endforelse
        </list-section>

        <list-section
            :header="'Invitations'.($invitations ? ' · '.count($invitations).' waiting' : '')"
            :footer="$this->canInvite() ? null : ($invitations ? 'They’ll join the team once they accept.' : null)"
        >
            @foreach ($invitations as $invitation)
                <list-item
                    ref="team-invitation-{{ $invitation['code'] }}"
                    :headline="$invitation['email']"
                    :supporting="$invitation['supporting']"
                    :leadingIconIos="Ios::Envelope"
                    :leadingIconAndroid="Android::Email"
                    :trailingIconIos="$this->canInvite() ? Ios::ChevronRight : null"
                    :trailingIconAndroid="$this->canInvite() ? Android::ChevronRight : null"
                    @navigate="'/preferences/team/invite'"
                />
            @endforeach
            @if ($this->canInvite())
                <list-item
                    ref="invite-someone"
                    headline="Invite someone"
                    :leadingIconIos="Ios::PlusCircle"
                    :leadingIconAndroid="Android::AddCircle"
                    :leadingIconColor="theme('primary-text')"
                    :headlineColor="theme('primary-text')"
                    @navigate="'/preferences/team/invite'"
                />
            @elseif (! $invitations)
                <list-item ref="no-invitations" headline="No one is waiting to join" />
            @endif
        </list-section>

        @if ($role === TeamRole::Owner)
            <list-section header="On the website" :footer="'To make someone else the owner or delete '.$team->name.', use the team page on the website.'">
                <list-item
                    ref="open-team-page"
                    headline="Open team page"
                    :supporting="$this->siteHost()"
                    :leadingIconIos="Ios::Globe"
                    :leadingIconAndroid="Android::Public"
                    :trailingIconIos="Ios::ArrowUpRight"
                    :trailingIconAndroid="Android::OpenInNew"
                    a11y-hint="Opens the Sunny website"
                    @tap="openTeamPage"
                />
            </list-section>
        @endif

        @if ($this->canLeave())
            <list-section :footer="$this->leaveFooter()">
                <list-item
                    ref="leave-team"
                    :headline="'Leave '.$team->name"
                    :leadingIconIos="Ios::RectanglePortraitAndArrowRight"
                    :leadingIconAndroid="Android::Logout"
                    :leadingIconColor="theme('destructive')"
                    :headlineColor="theme('destructive')"
                    :disabled="$offline"
                    @tap="confirmLeave"
                />
            </list-section>
        @endif
    </list>

    <native:bottom-sheet ref="team-rename-sheet" :visible="$showRename" @dismiss="closeRename" detents="medium">
        @if ($showRename)
            <column fill class="gap-4 bg-theme-background px-6 pt-6 pb-8">
                <x-ui.heading size="lg">Rename {{ $team->name }}</x-ui.heading>
                <outlined-text-input
                    ref="team-rename-input"
                    label="Name"
                    autocapitalize="words"
                    :ios-leading-icon="Ios::House"
                    :android-leading-icon="Android::Home"
                    :error="$renameError !== ''"
                    :supporting="$renameError !== '' ? $renameError : null"
                    native:model="newName"
                />
                <button ref="team-rename-save" variant="primary" size="lg" font="semibold" class="w-full" @tap="rename">Save name</button>
                <button ref="team-rename-cancel" variant="ghost" class="w-full" @tap="closeRename">Cancel</button>
            </column>
        @endif
    </native:bottom-sheet>

    <native:bottom-sheet ref="team-week-start-sheet" :visible="$showWeekStart" @dismiss="closeWeekStart" detents="medium">
        @if ($showWeekStart)
            <scroll-view fill class="bg-theme-background">
                <column class="w-full gap-4 px-6 pt-6 pb-8">
                    @include('native.option-list', [
                        'ref' => 'team-week-start-option',
                        'label' => 'Week starts on',
                        'options' => TeamSettings::WEEKDAYS,
                        'selected' => $team->week_start ?? 0,
                        'action' => 'chooseWeekStart',
                    ])
                </column>
            </scroll-view>
        @endif
    </native:bottom-sheet>

    <native:bottom-sheet ref="team-timezone-sheet" :visible="$showTimezone" @dismiss="closeTimezone" detents="large">
        @if ($showTimezone)
            <column fill class="bg-theme-background">
                <column class="w-full gap-3 px-4 pt-4 pb-2">
                    <row class="w-full items-center">
                        <pressable ref="team-timezone-cancel" class="min-h-12 w-20 justify-center" @tap="closeTimezone">
                            <text class="text-lg text-theme-primary-text">Cancel</text>
                        </pressable>
                        <text font="semibold" class="flex-1 text-center text-lg text-theme-on-surface">Timezone</text>
                        <column class="w-20" />
                    </row>
                    <outlined-text-input
                        ref="team-timezone-search"
                        placeholder="Find a city or region"
                        a11y-label="Find a timezone"
                        :ios-leading-icon="Ios::Magnifyingglass"
                        :android-leading-icon="Android::Search"
                        native:model.debounce.300ms="timezoneSearch"
                    />
                </column>
                <scroll-view ref="team-timezone-list" fill>
                    <column class="w-full px-4 pt-2 pb-6">
                        <column class="{{ Card::classes() }} w-full">
                            @forelse ($this->timezones() as $timezone)
                                <pressable
                                    ref="team-timezone-{{ $timezone }}"
                                    :a11y-label="str_replace('_', ' ', $timezone).($timezone === $team->timezone ? ', selected' : '')"
                                    class="min-h-12 w-full justify-center px-4 py-2"
                                    @tap="chooseTimezone('{{ $timezone }}')"
                                >
                                    <row class="w-full items-center gap-4">
                                        <text class="flex-1 text-base text-theme-on-surface">{{ str_replace('_', ' ', $timezone) }}</text>
                                        @if ($timezone === $team->timezone)
                                            <icon :ios="Ios::Checkmark" :android="Android::Check" :size="16" class="text-theme-primary-text" />
                                        @endif
                                    </row>
                                </pressable>
                                @unless ($loop->last)
                                    <divider class="ml-4" />
                                @endunless
                            @empty
                                <x-ui.text class="px-4 py-6 text-center">No timezone matches “{{ $timezoneSearch }}”.</x-ui.text>
                            @endforelse
                        </column>
                    </column>
                </scroll-view>
            </column>
        @endif
    </native:bottom-sheet>

    <native:bottom-sheet ref="team-member-sheet" :visible="$memberId !== null" @dismiss="closeMember" detents="medium">
        @if ($member = $this->member())
            <scroll-view fill class="bg-theme-background">
                <column class="w-full gap-4 px-6 pt-6 pb-8">
                    <row class="w-full items-center gap-4">
                        <x-ui.avatar size="lg" circle :initials="$member['initials']" color="red" />
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="xl">{{ $member['name'] }}</x-ui.heading>
                            <x-ui.text>{{ $member['role']->label() }} of {{ $team->name }}</x-ui.text>
                        </column>
                    </row>

                    @if ($confirmingRemove)
                        <column ref="member-remove-confirm" class="w-full gap-1">
                            <text font="semibold" class="text-base text-theme-on-surface">Remove {{ $this->firstName($member['name']) }} from {{ $team->name }}?</text>
                            <x-ui.text>{{ $this->firstName($member['name']) }} will lose access to the team’s recipes, inventory, routines and lists.</x-ui.text>
                        </column>
                        <button ref="member-remove-confirm-button" variant="destructive" size="lg" font="semibold" class="w-full" @tap="removeMember">Remove {{ $this->firstName($member['name']) }}</button>
                        <button ref="member-remove-keep" variant="ghost" class="w-full" @tap="keepMember">Keep {{ $this->firstName($member['name']) }}</button>
                    @else
                        <column class="w-full gap-2">
                            @include('native.role-options', ['ref' => 'member-role', 'roles' => [TeamRole::Admin, TeamRole::Member], 'selected' => $member['role'], 'action' => 'changeRole'])
                            <x-ui.text size="sm">Changes as soon as you pick one.</x-ui.text>
                        </column>

                        <pressable
                            ref="member-remove"
                            :a11y-label="'Remove from '.$team->name"
                            class="min-h-12 w-full items-center justify-center rounded-full border border-theme-outline bg-theme-surface px-4"
                            @tap="confirmRemove"
                        >
                            <text font="semibold" class="text-base text-theme-destructive">Remove from {{ $team->name }}</text>
                        </pressable>
                        <button ref="member-done" variant="ghost" class="w-full" @tap="closeMember">Done</button>
                    @endif
                </column>
            </scroll-view>
        @endif
    </native:bottom-sheet>
@endif
