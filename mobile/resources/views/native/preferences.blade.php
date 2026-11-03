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
    </list-section>
</list>
