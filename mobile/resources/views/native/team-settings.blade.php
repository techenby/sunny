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
                    @include('native.settings-row', [
                        'ref' => 'team-offline',
                        'headline' => 'You’re offline',
                        'supporting' => 'This is '.$team->name.($this->syncedAt() ? ' as of '.$this->syncedAt() : '').'. Changing the team needs a connection, so it’s look-only until you’re back.',
                        'ios' => Ios::WifiSlash,
                        'android' => Android::WifiOff,
                        'tone' => 'primary',
                        'trailing' => 'refresh',
                        'hint' => 'Checks the connection again',
                        'tap' => 'retry',
                        'last' => true,
                    ])
                </column>
            @endif

            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Team</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    @include('native.settings-row', [
                        'ref' => 'team-name',
                        'headline' => $team->name,
                        'supporting' => 'Name',
                        'monogram' => mb_strtoupper(mb_substr($team->name, 0, 1)),
                        'trailingText' => $role?->canUpdateTeam() ? 'Rename' : null,
                        'disabled' => $role?->canUpdateTeam() && ! $this->canEdit(),
                        'hint' => $this->canEdit() ? 'Renames the team' : null,
                        'tap' => 'openRename',
                        'last' => true,
                    ])
                </column>
            </column>

            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Household</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    @include('native.settings-row', [
                        'ref' => 'team-timezone',
                        'headline' => 'Timezone',
                        'supporting' => $team->timezone ?? 'Not set',
                        'ios' => Ios::Globe,
                        'android' => Android::Public,
                        'trailing' => $role?->canUpdateTeam() ? 'chevron' : null,
                        'disabled' => $role?->canUpdateTeam() && ! $this->canEdit(),
                        'tap' => 'openTimezone',
                    ])
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
                        @include('native.settings-row', [
                            'ref' => 'member-'.$member['id'],
                            'headline' => $member['name'],
                            'supporting' => $member['role']->label().($member['you'] ? ' · You' : ''),
                            'monogram' => $member['initials'],
                            'trailing' => $member['tappable'] ? 'chevron' : null,
                            'disabled' => $member['tappable'] && $offline,
                            'hint' => $member['tappable'] ? 'Changes their role or removes them' : null,
                            'tap' => 'openMember('.$member['id'].')',
                            'last' => $loop->last,
                        ])
                    @empty
                        @include('native.settings-row', ['ref' => 'no-members', 'headline' => 'No members downloaded yet', 'last' => true])
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
                        @include('native.settings-row', [
                            'ref' => 'team-invitation-'.$invitation['code'],
                            'headline' => $invitation['email'],
                            'supporting' => $invitation['supporting'],
                            'ios' => Ios::Envelope,
                            'android' => Android::Email,
                            'trailing' => $this->canInvite() ? 'chevron' : null,
                            'url' => '/preferences/team/invite',
                            'last' => $loop->last && ! $this->canInvite(),
                        ])
                    @endforeach
                    @if ($this->canInvite())
                        @include('native.settings-row', [
                            'ref' => 'invite-someone',
                            'headline' => 'Invite someone',
                            'ios' => Ios::PlusCircle,
                            'android' => Android::AddCircle,
                            'tone' => 'primary',
                            'url' => '/preferences/team/invite',
                            'last' => true,
                        ])
                    @elseif (! $invitations)
                        @include('native.settings-row', ['ref' => 'no-invitations', 'headline' => 'No one is waiting to join', 'last' => true])
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
                        @include('native.settings-row', [
                            'ref' => 'open-team-page',
                            'headline' => 'Open team page',
                            'supporting' => $this->siteHost(),
                            'ios' => Ios::Globe,
                            'android' => Android::Public,
                            'trailing' => 'external',
                            'hint' => 'Opens the website',
                            'tap' => 'openTeamPage',
                            'last' => true,
                        ])
                    </column>
                    <x-ui.text size="sm" class="px-1">To make someone else the owner or delete {{ $team->name }}, use the team page on the website.</x-ui.text>
                </column>
            @endif

            @if ($this->canLeave())
                <column class="w-full gap-2">
                    <column class="{{ Card::classes() }} w-full">
                        @include('native.settings-row', [
                            'ref' => 'leave-team',
                            'headline' => 'Leave '.$team->name,
                            'ios' => Ios::RectanglePortraitAndArrowRight,
                            'android' => Android::Logout,
                            'tone' => 'destructive',
                            'disabled' => $offline,
                            'tap' => 'confirmLeave',
                            'last' => true,
                        ])
                    </column>
                    @if ($this->leaveFooter())
                        <x-ui.text size="sm" class="px-1">{{ $this->leaveFooter() }}</x-ui.text>
                    @endif
                </column>
            @endif
        </column>
    </scroll-view>

    <native:bottom-sheet ref="team-rename-sheet" :visible="$showRename" @dismiss="closeRename" detents="medium">
        @if ($showRename)
            <scroll-view fill class="bg-theme-background">
                <column class="w-full gap-4 px-6 pt-6 pb-8">
                    <x-ui.heading size="lg">Rename {{ $team->name }}</x-ui.heading>
                    <outlined-text-input
                        ref="team-rename-input"
                        label="Name"
                        autocapitalize="words"
                        :error="$renameError !== ''"
                        :supporting="$renameError !== '' ? $renameError : null"
                        native:model="newName"
                    />
                    <x-ui.button ref="team-rename-save" class="w-full" tap="rename">Save</x-ui.button>
                    <x-ui.button ref="team-rename-cancel" variant="text" class="w-full" tap="closeRename">Cancel</x-ui.button>
                </column>
            </scroll-view>
        @endif
    </native:bottom-sheet>

    <native:bottom-sheet ref="team-timezone-sheet" :visible="$showTimezone" @dismiss="closeTimezone" detents="large">
        @if ($showTimezone)
            <column fill class="bg-theme-background">
                <column class="w-full gap-3 px-4 pt-4 pb-2">
                    <row class="w-full items-center">
                        <x-ui.button
                            ref="team-timezone-cancel"
                            variant="text"
                            class="w-20"
                            tap="closeTimezone"
                        >Cancel</x-ui.button>
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
                        <x-ui.button ref="member-remove-confirm-button" variant="destructive" class="w-full" tap="removeMember">Remove {{ $this->firstName($member['name']) }}</x-ui.button>
                        <x-ui.button ref="member-remove-keep" variant="text" class="w-full" tap="keepMember">Keep {{ $this->firstName($member['name']) }}</x-ui.button>
                    @else
                        <column class="w-full gap-2">
                            @include('native.role-options', ['ref' => 'member-role', 'roles' => [TeamRole::Admin, TeamRole::Member], 'selected' => $member['role'], 'action' => 'changeRole'])
                            <x-ui.text size="sm">Changes as soon as you pick one.</x-ui.text>
                        </column>

                        <pressable
                            ref="member-remove"
                            :a11y-label="'Remove from '.$team->name"
                            class="min-h-12 w-full items-center justify-center rounded-3xl border border-theme-outline bg-theme-surface px-4"
                            @tap="confirmRemove"
                        >
                            <text font="semibold" class="text-center text-base text-theme-destructive">Remove from {{ $team->name }}</text>
                        </pressable>
                        <x-ui.button ref="member-done" variant="text" class="w-full" tap="closeMember">Done</x-ui.button>
                    @endif
                </column>
            </scroll-view>
        @endif
    </native:bottom-sheet>
@endif
