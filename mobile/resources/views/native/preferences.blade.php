@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Settings" back />

<scroll-view ref="preferences" fill class="bg-theme-background">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        <column class="w-full gap-2">
            <x-ui.subheading class="px-1">Account</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @include('native.settings-row', [
                    'ref' => 'account',
                    'headline' => $profile['name'] ?? 'Name and email',
                    'supporting' => $profile['email'] ?? null,
                    'monogram' => $profile ? $this->initials($profile['name']) : null,
                    'ios' => Ios::PersonCropCircle,
                    'android' => Android::AccountCircle,
                    'trailing' => 'chevron',
                    'url' => '/preferences/account',
                ])
                @include('native.settings-row', [
                    'ref' => 'password',
                    'headline' => 'Password',
                    'ios' => Ios::Key,
                    'android' => Android::Key,
                    'trailing' => 'chevron',
                    'url' => '/preferences/password',
                ])
                @include('native.settings-row', [
                    'ref' => 'security-settings',
                    'headline' => 'Security settings',
                    'ios' => Ios::CheckmarkShield,
                    'android' => Android::Security,
                    'trailing' => 'external',
                    'hint' => 'Opens the Sunny website',
                    'tap' => 'openSecuritySettings',
                    'last' => true,
                ])
            </column>
            <x-ui.text size="sm" class="px-1">Two-factor authentication, passkeys and API tokens open on {{ $this->siteHost() }}.</x-ui.text>
        </column>

        @if ($this->invitations)
            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Invitations</x-ui.subheading>
                @foreach ($this->invitations as $invitation)
                    <column class="{{ Card::classes() }} w-full">
                        @include('native.settings-row', [
                            'ref' => 'invitation-'.$invitation['code'],
                            'headline' => ($invitation['inviter'] ? $invitation['inviter'].' invited you to join ' : 'You’re invited to join ').$invitation['team'],
                            'supporting' => $invitation['expires'],
                            'monogram' => $invitation['initials'],
                        ])
                        @include('native.settings-row', [
                            'ref' => 'accept-invitation-'.$invitation['code'],
                            'headline' => 'Accept',
                            'ios' => Ios::CheckmarkCircle,
                            'android' => Android::CheckCircle,
                            'tone' => 'primary',
                            'hint' => 'Joins '.$invitation['team'],
                            'tap' => "acceptInvitation('".$invitation['code']."')",
                        ])
                        @include('native.settings-row', [
                            'ref' => 'decline-invitation-'.$invitation['code'],
                            'headline' => 'Decline',
                            'ios' => Ios::XmarkCircle,
                            'android' => Android::Cancel,
                            'hint' => 'Turns down the invitation to '.$invitation['team'],
                            'tap' => "declineInvitation('".$invitation['code']."')",
                            'last' => true,
                        ])
                    </column>
                @endforeach
            </column>
        @endif

        <column class="w-full gap-2">
            <x-ui.subheading class="px-1">Team</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @forelse ($this->teamOptions as $id => $name)
                    @include('native.settings-row', [
                        'ref' => 'team-'.$id,
                        'headline' => $name,
                        'monogram' => mb_strtoupper(mb_substr($name, 0, 1)),
                        'trailing' => $id === $activeTeamId ? 'check' : null,
                        'tap' => 'selectTeam('.$id.')',
                        'last' => $loop->last,
                    ])
                @empty
                    @include('native.settings-row', ['ref' => 'no-teams', 'headline' => 'No teams available', 'last' => true])
                @endforelse
            </column>
            <x-ui.text size="sm" class="px-1">{{ $this->teamOptions ? 'Recipes, inventory, routines and lists show for the team you pick.' : 'Pull down on Summary to download your teams.' }}</x-ui.text>
        </column>

        <column class="{{ Card::classes() }} w-full">
            @if ($this->currentTeam)
                @include('native.settings-row', [
                    'ref' => 'team-settings',
                    'headline' => $this->currentTeam['name'],
                    'supporting' => $this->currentTeam['summary'],
                    'ios' => Ios::Person2Circle,
                    'android' => Android::Group,
                    'trailing' => 'chevron',
                    'url' => '/preferences/team',
                ])
            @endif
            @include('native.settings-row', [
                'ref' => 'new-team',
                'headline' => 'New team',
                'ios' => Ios::PlusCircle,
                'android' => Android::AddCircle,
                'tone' => 'primary',
                'url' => '/preferences/teams/create',
                'last' => true,
            ])
        </column>

        @if ($canShareCrashReports)
            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Privacy</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    @include('native.settings-row', [
                        'ref' => 'crash-reports',
                        'headline' => 'Share crash reports',
                        'ios' => Ios::Ladybug,
                        'android' => Android::BugReport,
                        'toggle' => ['value' => $shareCrashReports, 'change' => 'setCrashReports'],
                        'last' => true,
                    ])
                </column>
                <x-ui.text size="sm" class="px-1">Sends error details and information about your device to NativeWatch so we can fix problems. Off unless you turn it on.</x-ui.text>
            </column>
        @endif

        <column class="w-full gap-2">
            <x-ui.subheading class="px-1">About</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @include('native.settings-row', [
                    'ref' => 'privacy-policy',
                    'headline' => 'Privacy policy',
                    'ios' => Ios::HandRaised,
                    'android' => Android::Policy,
                    'trailing' => 'external',
                    'hint' => 'Opens the Sunny website',
                    'tap' => 'openPrivacyPolicy',
                ])
                @include('native.settings-row', [
                    'ref' => 'terms',
                    'headline' => 'Terms of service',
                    'ios' => Ios::DocText,
                    'android' => Android::Description,
                    'trailing' => 'external',
                    'hint' => 'Opens the Sunny website',
                    'tap' => 'openTerms',
                    'last' => true,
                ])
            </column>
        </column>

        <column class="{{ Card::classes() }} w-full">
            @include('native.settings-row', [
                'ref' => 'log-out',
                'headline' => 'Log out',
                'ios' => Ios::RectanglePortraitAndArrowRight,
                'android' => Android::Logout,
                'tone' => 'destructive',
                'tap' => 'confirmLogOut',
            ])
            @include('native.settings-row', [
                'ref' => 'delete-account',
                'headline' => 'Delete account',
                'ios' => Ios::PersonCropCircleBadgeMinus,
                'android' => Android::PersonRemove,
                'tone' => 'destructive',
                'trailing' => 'chevron',
                'url' => '/preferences/delete-account',
                'last' => true,
            ])
        </column>

        @if (app()->environment('local'))
            <column ref="developer" class="w-full gap-2">
                <x-ui.subheading class="px-1">Developer</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    @include('native.settings-row', [
                        'ref' => 'ui-kit',
                        'headline' => 'UI kit',
                        'ios' => Ios::Swatchpalette,
                        'android' => Android::Palette,
                        'trailing' => 'chevron',
                        'url' => '/ui-kit',
                        'last' => true,
                    ])
                </column>
                <x-ui.text size="sm" class="px-1">Only on development builds.</x-ui.text>
            </column>
        @endif
    </column>
</scroll-view>
