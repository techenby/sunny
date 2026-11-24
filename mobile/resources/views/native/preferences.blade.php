@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Settings" back />

<scroll-view ref="preferences" fill class="bg-theme-background">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        <column class="w-full gap-2">
            <x-ui.subheading class="px-1">Account</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                <x-ui.row
                    ref="account"
                    :headline="$profile['name'] ?? 'Name and email'"
                    :supporting="$profile['email'] ?? null"
                    :monogram="$profile ? $this->initials($profile['name']) : null"
                    :ios="Ios::PersonCropCircle"
                    :android="Android::AccountCircle"
                    trailing="chevron"
                    url="/preferences/account"
                />
                <x-ui.row
                    ref="password"
                    headline="Password"
                    :ios="Ios::Key"
                    :android="Android::Key"
                    trailing="chevron"
                    url="/preferences/password"
                />
                <x-ui.row
                    ref="security-settings"
                    headline="Security settings"
                    :ios="Ios::CheckmarkShield"
                    :android="Android::Security"
                    trailing="external"
                    hint="Opens the website"
                    tap="openSecuritySettings"
                    last
                />
            </column>
            <x-ui.text size="sm" class="px-1">Two-factor authentication, passkeys and API tokens open on {{ $this->siteHost() }}.</x-ui.text>
        </column>

        @if ($this->invitations)
            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Invitations</x-ui.subheading>
                @foreach ($this->invitations as $invitation)
                    <column class="{{ Card::classes() }} w-full">
                        <x-ui.row
                            :ref="'invitation-'.$invitation['code']"
                            :headline="($invitation['inviter'] ? $invitation['inviter'].' invited you to join ' : 'You’re invited to join ').$invitation['team']"
                            :supporting="$invitation['expires']"
                            :monogram="$invitation['initials']"
                        />
                        <x-ui.row
                            :ref="'accept-invitation-'.$invitation['code']"
                            headline="Accept"
                            :ios="Ios::CheckmarkCircle"
                            :android="Android::CheckCircle"
                            tone="add"
                            :hint="'Joins '.$invitation['team']"
                            :tap="'acceptInvitation(\''.$invitation['code'].'\')'"
                        />
                        <x-ui.row
                            :ref="'decline-invitation-'.$invitation['code']"
                            headline="Decline"
                            :ios="Ios::XmarkCircle"
                            :android="Android::Cancel"
                            :hint="'Turns down the invitation to '.$invitation['team']"
                            :tap="'declineInvitation(\''.$invitation['code'].'\')'"
                            last
                        />
                    </column>
                @endforeach
            </column>
        @endif

        <column class="w-full gap-2">
            <x-ui.subheading class="px-1">Team</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @forelse ($this->teamOptions as $id => $name)
                    <x-ui.row
                        :ref="'team-'.$id"
                        :headline="$name"
                        :monogram="mb_strtoupper(mb_substr($name, 0, 1))"
                        :trailing="$id === $activeTeamId ? 'check' : null"
                        :tap="'selectTeam('.$id.')'"
                        :last="$loop->last"
                    />
                @empty
                    <x-ui.row
                        ref="no-teams"
                        headline="No teams available"
                        last
                    />
                @endforelse
            </column>
            <x-ui.text size="sm" class="px-1">{{ $this->teamOptions ? 'Recipes, inventory, routines and lists show for the team you pick.' : 'Pull down on Summary to download your teams.' }}</x-ui.text>
        </column>

        <column class="{{ Card::classes() }} w-full">
            @if ($this->currentTeam)
                <x-ui.row
                    ref="team-settings"
                    :headline="$this->currentTeam['name']"
                    :supporting="$this->currentTeam['summary']"
                    :ios="Ios::Person2Circle"
                    :android="Android::Group"
                    trailing="chevron"
                    url="/preferences/team"
                />
            @endif
            <x-ui.row
                ref="new-team"
                headline="New team"
                :ios="Ios::PlusCircle"
                :android="Android::AddCircle"
                tone="add"
                url="/preferences/teams/create"
                last
            />
        </column>

        @if ($canShareCrashReports)
            <column class="w-full gap-2">
                <x-ui.subheading class="px-1">Privacy</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    <x-ui.row
                        ref="crash-reports"
                        headline="Share crash reports"
                        :ios="Ios::Ladybug"
                        :android="Android::BugReport"
                        :toggle="['value' => $shareCrashReports, 'change' => 'setCrashReports']"
                        last
                    />
                </column>
                <x-ui.text size="sm" class="px-1">Sends error details and information about your device to NativeWatch so we can fix problems. Off unless you turn it on.</x-ui.text>
            </column>
        @endif

        <column class="w-full gap-2">
            <x-ui.subheading class="px-1">About</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                <x-ui.row
                    ref="privacy-policy"
                    headline="Privacy policy"
                    :ios="Ios::HandRaised"
                    :android="Android::Policy"
                    trailing="external"
                    hint="Opens the website"
                    tap="openPrivacyPolicy"
                />
                <x-ui.row
                    ref="terms"
                    headline="Terms of service"
                    :ios="Ios::DocText"
                    :android="Android::Description"
                    trailing="external"
                    hint="Opens the website"
                    tap="openTerms"
                    last
                />
            </column>
        </column>

        <column class="{{ Card::classes() }} w-full">
            <x-ui.row
                ref="log-out"
                headline="Log out"
                :ios="Ios::RectanglePortraitAndArrowRight"
                :android="Android::Logout"
                tone="destructive"
                tap="confirmLogOut"
            />
            <x-ui.row
                ref="delete-account"
                headline="Delete account"
                :ios="Ios::PersonCropCircleBadgeMinus"
                :android="Android::PersonRemove"
                tone="destructive"
                trailing="chevron"
                url="/preferences/delete-account"
                last
            />
        </column>

        @if (app()->environment('local'))
            <column ref="developer" class="w-full gap-2">
                <x-ui.subheading class="px-1">Developer</x-ui.subheading>
                <column class="{{ Card::classes() }} w-full">
                    <x-ui.row
                        ref="ui-kit"
                        headline="UI kit"
                        :ios="Ios::Swatchpalette"
                        :android="Android::Palette"
                        trailing="chevron"
                        url="/ui-kit"
                        last
                    />
                </column>
                <x-ui.text size="sm" class="px-1">Only on development builds.</x-ui.text>
            </column>
        @endif
    </column>
</scroll-view>
