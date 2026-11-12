@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Preferences" back />

<list ref="preferences" fill class="bg-theme-background">
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

    <list-section footer="Deleting your account happens on the Sunny website. Sign in there, then choose Delete account at the bottom of your profile settings.">
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
            :trailingIconIos="Ios::ArrowUpRight"
            :trailingIconAndroid="Android::OpenInNew"
            a11y-hint="Opens your profile settings on the Sunny website"
            @tap="deleteAccount"
        />
    </list-section>
</list>
