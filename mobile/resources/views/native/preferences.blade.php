@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Preferences" back />

<list ref="preferences" fill class="bg-theme-background">
    <list-section header="Account" footer="Two-factor authentication, passkeys and API tokens open on {{ $this->siteHost() }}.">
        @if ($profile)
            <list-item
                ref="account"
                :headline="$profile['name']"
                :supporting="$profile['email']"
                :leadingMonogram="$this->initials($profile['name'])"
                :leadingMonogramColor="theme('primary')"
                :trailingIconIos="Ios::ChevronRight"
                :trailingIconAndroid="Android::ChevronRight"
                @navigate="'/preferences/account'"
            />
        @else
            <list-item
                ref="account"
                headline="Name and email"
                :leadingIconIos="Ios::PersonCropCircle"
                :leadingIconAndroid="Android::AccountCircle"
                :trailingIconIos="Ios::ChevronRight"
                :trailingIconAndroid="Android::ChevronRight"
                @navigate="'/preferences/account'"
            />
        @endif
        <list-item
            ref="password"
            headline="Password"
            :leadingIconIos="Ios::Key"
            :leadingIconAndroid="Android::Key"
            :trailingIconIos="Ios::ChevronRight"
            :trailingIconAndroid="Android::ChevronRight"
            @navigate="'/preferences/password'"
        />
        <list-item
            ref="security-settings"
            headline="Security settings"
            :leadingIconIos="Ios::CheckmarkShield"
            :leadingIconAndroid="Android::Security"
            :trailingIconIos="Ios::ArrowUpRight"
            :trailingIconAndroid="Android::OpenInNew"
            a11y-hint="Opens the Sunny website"
            @tap="openSecuritySettings"
        />
    </list-section>

    <list-section header="Team" :footer="$this->teamOptions ? 'Recipes, inventory, routines and lists show for the team you pick.' : 'Pull down on Summary to download your teams.'">
        @forelse ($this->teamOptions as $id => $name)
            <list-item
                ref="team-{{ $id }}"
                :headline="$name"
                :leadingMonogram="mb_strtoupper(mb_substr($name, 0, 1))"
                :leadingMonogramColor="theme('primary')"
                :trailingIconIos="$id === $activeTeamId ? Ios::Checkmark : null"
                :trailingIconAndroid="$id === $activeTeamId ? Android::Check : null"
                :trailingIconColor="theme('primary-text')"
                @tap="selectTeam({{ $id }})"
            />
        @empty
            <list-item ref="no-teams" headline="No teams available" />
        @endforelse
    </list-section>

    @if ($canShareCrashReports)
        <list-section header="Privacy" footer="Sends error details and information about your device to NativeWatch so we can fix problems. Off unless you turn it on.">
            <list-item
                ref="crash-reports"
                headline="Share crash reports"
                :leadingIconIos="Ios::Ladybug"
                :leadingIconAndroid="Android::BugReport"
                :trailingSwitch="$shareCrashReports"
                onTrailingChange="setCrashReports"
            />
        </list-section>
    @endif

    <list-section header="About">
        <list-item
            ref="privacy-policy"
            headline="Privacy policy"
            :leadingIconIos="Ios::HandRaised"
            :leadingIconAndroid="Android::Policy"
            :trailingIconIos="Ios::ArrowUpRight"
            :trailingIconAndroid="Android::OpenInNew"
            a11y-hint="Opens the Sunny website"
            @tap="openPrivacyPolicy"
        />
        <list-item
            ref="terms"
            headline="Terms of service"
            :leadingIconIos="Ios::DocText"
            :leadingIconAndroid="Android::Description"
            :trailingIconIos="Ios::ArrowUpRight"
            :trailingIconAndroid="Android::OpenInNew"
            a11y-hint="Opens the Sunny website"
            @tap="openTerms"
        />
    </list-section>

    <list-section>
        <list-item
            ref="log-out"
            headline="Log out"
            :leadingIconIos="Ios::RectanglePortraitAndArrowRight"
            :leadingIconAndroid="Android::Logout"
            :leadingIconColor="theme('destructive')"
            :headlineColor="theme('destructive')"
            @tap="confirmLogOut"
        />
        <list-item
            ref="delete-account"
            headline="Delete account"
            :leadingIconIos="Ios::PersonCropCircleBadgeMinus"
            :leadingIconAndroid="Android::PersonRemove"
            :leadingIconColor="theme('destructive')"
            :headlineColor="theme('destructive')"
            :trailingIconIos="Ios::ChevronRight"
            :trailingIconAndroid="Android::ChevronRight"
            @navigate="'/preferences/delete-account'"
        />
    </list-section>
</list>
