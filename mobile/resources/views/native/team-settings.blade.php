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
    <scroll-view ref="team-settings" fill class="bg-theme-background">
        <column class="w-full gap-6 px-4 pt-2 pb-8">
            @if ($offline)
                <column class="{{ Card::classes() }} w-full">
                    <x-ui.row
                        ref="team-offline"
                        headline="You’re offline"
                        :supporting="'This is '.$team->name.($this->syncedAt() ? ' as of '.$this->syncedAt() : '').'. Changing the team needs a connection, so it’s look-only until you’re back.'"
                        :ios="Ios::WifiSlash"
                        :android="Android::WifiOff"
                        trailing="refresh"
                        hint="Checks the connection again"
                        tap="retry"
                        last
                    />
                </column>
            @endif

            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Team</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    <x-ui.row
                        ref="team-name"
                        :headline="$team->name"
                        supporting="Name"
                        :monogram="mb_strtoupper(mb_substr($team->name, 0, 1))"
                        :trailing-text="$role?->canUpdateTeam() ? 'Rename' : null"
                        :disabled="$role?->canUpdateTeam() && ! $this->canEdit()"
                        :hint="$this->canEdit() ? 'Renames the team' : null"
                        tap="openRename"
                        last
                    />
                </column>
            </column>

            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Household</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    <x-ui.row
                        ref="team-timezone"
                        headline="Timezone"
                        :supporting="$team->timezone ?? 'Not set'"
                        :ios="Ios::Globe"
                        :android="Android::Public"
                        :trailing="$role?->canUpdateTeam() ? 'chevron' : null"
                        :disabled="$role?->canUpdateTeam() && ! $this->canEdit()"
                        tap="openTimezone"
                    />
                    <column class="w-full px-4 py-3">
                        <select
                            ref="team-week-start"
                            label="Week starts on"
                            :options="TeamSettings::WEEKDAYS"
                            :value="$this->weekStart()"
                            :disabled="! $this->canEdit()"
                            @change="pickWeekStart"
                        />
                    </column>
                </column>
                <x-ui.text size="sm" class="px-1">Routines reset at midnight in this timezone, so everyone’s day starts together.</x-ui.text>
            </column>

            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">{{ 'Members'.($members ? ' · '.count($members) : '') }}</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    @forelse ($members as $member)
                        <x-ui.row
                            :ref="'member-'.$member['id']"
                            :headline="$member['name']"
                            :supporting="$member['role']->label().($member['you'] ? ' · You' : '')"
                            :monogram="$member['initials']"
                            :trailing="$member['tappable'] ? 'chevron' : null"
                            :disabled="$member['tappable'] && $offline"
                            :hint="$member['tappable'] ? 'Changes their role or removes them' : null"
                            :tap="'openMember('.$member['id'].')'"
                            :last="$loop->last"
                        />
                    @empty
                        <x-ui.row
                            ref="no-members"
                            headline="No members downloaded yet"
                            last
                        />
                    @endforelse
                </column>
                @if ($this->membersFooter())
                    <x-ui.text size="sm" class="px-1">{{ $this->membersFooter() }}</x-ui.text>
                @endif
            </column>

            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">{{ 'Invitations'.($invitations ? ' · '.count($invitations).' waiting' : '') }}</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    @foreach ($invitations as $invitation)
                        <x-ui.row
                            :ref="'team-invitation-'.$invitation['code']"
                            :headline="$invitation['email']"
                            :supporting="$invitation['supporting']"
                            :ios="Ios::Envelope"
                            :android="Android::Email"
                            :trailing="$this->canInvite() ? 'chevron' : null"
                            url="/preferences/team/invite"
                            :last="$loop->last && ! $this->canInvite()"
                        />
                    @endforeach
                    @if ($this->canInvite())
                        <x-ui.row
                            ref="invite-someone"
                            headline="Invite someone"
                            :ios="Ios::PlusCircle"
                            :android="Android::AddCircle"
                            tone="add"
                            url="/preferences/team/invite"
                            last
                        />
                    @elseif (! $invitations)
                        <x-ui.row
                            ref="no-invitations"
                            headline="No one is waiting to join"
                            last
                        />
                    @endif
                </column>
                @if (! $this->canInvite() && $invitations)
                    <x-ui.text size="sm" class="px-1">They’ll join the team once they accept.</x-ui.text>
                @endif
            </column>

            @if ($role === TeamRole::Owner)
                <column class="w-full gap-2">
                    <x-ui.subheading class="px-1">On the website</x-ui.subheading>
                    <column class="{{ Card::classes() }} w-full">
                        <x-ui.row
                            ref="open-team-page"
                            headline="Open team page"
                            :supporting="$this->siteHost()"
                            :ios="Ios::Globe"
                            :android="Android::Public"
                            trailing="external"
                            hint="Opens the website"
                            tap="openTeamPage"
                            last
                        />
                    </column>
                    <x-ui.text size="sm" class="px-1">To make someone else the owner or delete {{ $team->name }}, use the team page on the website.</x-ui.text>
                </column>
            @endif

            @if ($this->canLeave())
                <column class="w-full gap-2">
                    <column class="{{ Card::classes() }} w-full">
                        <x-ui.row
                            ref="leave-team"
                            :headline="'Leave '.$team->name"
                            :ios="Ios::RectanglePortraitAndArrowRight"
                            :android="Android::Logout"
                            tone="destructive"
                            :disabled="$offline"
                            tap="confirmLeave"
                            last
                        />
                    </column>
                    @if ($this->leaveFooter())
                        <x-ui.text size="sm" class="px-1">{{ $this->leaveFooter() }}</x-ui.text>
                    @endif
                </column>
            @endif
        </column>
    </scroll-view>

    <native:bottom-sheet ref="team-rename-sheet" :visible="$showRename" @dismiss="closeRename" detents="fit">
        @if ($showRename)
            <column class="w-full bg-theme-background">
                <x-ui.sheet-header ref="team-rename-close" :title="'Rename '.$team->name" close="closeRename" />
                <column class="w-full px-4 pt-4">
                    <outlined-text-input
                        ref="team-rename-input"
                        label="Name"
                        autocapitalize="words"
                        :error="$renameError !== ''"
                        :supporting="$renameError !== '' ? $renameError : null"
                        native:model="newName"
                    />
                </column>
                <x-ui.sheet-confirm ref="team-rename-save" tap="rename">Save</x-ui.sheet-confirm>
            </column>
        @endif
    </native:bottom-sheet>

    <native:bottom-sheet ref="team-timezone-sheet" :visible="$showTimezone" @dismiss="closeTimezone" detents="large">
        @if ($showTimezone)
            <column fill class="bg-theme-background">
                <x-ui.sheet-header ref="team-timezone-close" title="Timezone" close="closeTimezone" />
                <column class="w-full px-4 pt-1 pb-2">
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

    <native:bottom-sheet ref="team-member-sheet" :visible="$memberId !== null" @dismiss="closeMember" detents="fit">
        @if ($member = $this->member())
            <column class="w-full bg-theme-background">
                <x-ui.sheet-header ref="member-close" :title="$member['name']" close="closeMember" />
                <column class="w-full gap-4 px-4 pt-4 pb-6 ios:pb-0">
                    <row class="w-full items-center gap-4">
                        <x-ui.avatar size="lg" circle :initials="$member['initials']" color="red" />
                        <x-ui.text class="flex-1">{{ $member['role']->label() }} of {{ $team->name }}</x-ui.text>
                    </row>

                    <column class="w-full gap-2">
                        @include('native.role-options', ['ref' => 'member-role', 'roles' => [TeamRole::Admin, TeamRole::Member], 'selected' => $member['role'], 'action' => 'changeRole'])
                        <x-ui.text size="sm">Changes as soon as you pick one.</x-ui.text>
                    </column>

                    <column class="{{ Card::classes() }} w-full">
                        <x-ui.row
                            ref="member-remove"
                            :headline="'Remove from '.$team->name"
                            :ios="Ios::PersonBadgeMinus"
                            :android="Android::PersonRemove"
                            tone="destructive"
                            tap="confirmRemove"
                            last
                        />
                    </column>
                </column>
            </column>
        @endif
    </native:bottom-sheet>
@endif
