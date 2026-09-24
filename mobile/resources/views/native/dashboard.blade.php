@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Dashboard" :back="false">
    <native:top-bar-action
        id="log-out"
        label="Log out"
        :ios-icon="Ios::RectanglePortraitAndArrowRight"
        :android-icon="Android::Logout"
        @tap="confirmLogOut"
    />
</native:top-bar>

<list ref="dashboard" fill class="bg-theme-background" @refresh="sync">
    @if ($syncError)
        <list-section>
            <list-item
                ref="sync-error"
                headline="Download failed"
                :supporting="$syncError"
                :supportingColor="theme('destructive')"
                :leadingIconIos="Ios::ExclamationmarkTriangle"
                :leadingIconAndroid="Android::Warning"
                :leadingIconColor="theme('destructive')"
                @tap="sync"
            />
        </list-section>
    @endif
    <list-section header="Team">
        @if ($this->teamOptions)
            <list-item
                ref="active-team"
                :headline="$activeTeamName"
                supporting="Active team"
                :leadingIconIos="Ios::Person3"
                :leadingIconAndroid="Android::Groups"
                :trailingIconIos="count($this->teamOptions) > 1 ? Ios::ChevronUpChevronDown : null"
                :trailingIconAndroid="count($this->teamOptions) > 1 ? Android::UnfoldMore : null"
                @tap="openTeamPicker"
            />
        @elseif ($this->teamOptions)
            <list-item
                ref="active-team"
                :headline="$activeTeamName"
                supporting="Active team"
                :leadingIconIos="Ios::Person3"
                :leadingIconAndroid="Android::Groups"
            />
        @else
            <list-item headline="No teams available" supporting="Pull down to download your teams." />
        @endif
    </list-section>
    <list-section
        header="Recent recipes"
        :footer="$this->recentRecipes ? null : 'Recipes you add or update will show up here.'"
    >
        @foreach ($this->recentRecipes as $recipe)
            <list-item
                ref="dashboard-recipes-{{ $recipe['id'] }}"
                :headline="$recipe['name']"
                :supporting="$recipe['supporting']"
                :leadingIconIos="Ios::ForkKnife"
                :leadingIconAndroid="Android::Restaurant"
                :trailingIconIos="Ios::ChevronRight"
                :trailingIconAndroid="Android::ChevronRight"
                @navigate($recipe['url'])
            />
        @endforeach
        <list-item
            ref="dashboard-recipes-all"
            headline="All recipes"
            :leadingIconIos="Ios::BookPages"
            :leadingIconAndroid="Android::MenuBook"
            :trailingIconIos="Ios::ChevronRight"
            :trailingIconAndroid="Android::ChevronRight"
            @navigate('/recipes')
        />
        <list-item
            ref="dashboard-recipes-create"
            headline="New recipe"
            :leadingIconIos="Ios::Plus"
            :leadingIconAndroid="Android::Add"
            :leadingIconColor="theme('primary')"
            :headlineColor="theme('primary')"
            @navigate('/recipes/create')
        />
    </list-section>

    <list-section
        header="Recent items"
        :footer="$this->recentItems ? null : 'Items you add or update will show up here.'"
    >
        @foreach ($this->recentItems as $item)
            <list-item
                ref="dashboard-inventory-{{ $item['id'] }}"
                :headline="$item['name']"
                :supporting="$item['supporting']"
                :leadingIconIos="$item['type']->iosIcon()"
                :leadingIconAndroid="$item['type']->androidIcon()"
                :leadingIconColor="$item['type']->iconColor()"
                :trailingIconIos="Ios::ChevronRight"
                :trailingIconAndroid="Android::ChevronRight"
                @navigate($item['url'])
            />
        @endforeach
        <list-item
            ref="dashboard-inventory-all"
            headline="All items"
            :leadingIconIos="Ios::Archivebox"
            :leadingIconAndroid="Android::Inventory2"
            :trailingIconIos="Ios::ChevronRight"
            :trailingIconAndroid="Android::ChevronRight"
            @navigate('/inventory')
        />
        <list-item
            ref="dashboard-inventory-create"
            headline="New item"
            :leadingIconIos="Ios::Plus"
            :leadingIconAndroid="Android::Add"
            :leadingIconColor="theme('primary')"
            :headlineColor="theme('primary')"
            @navigate('/inventory/create')
        />
    </list-section>

</list>

<native:bottom-sheet ref="team-picker" :visible="$showTeamPicker" @dismiss="closeTeamPicker" detents="medium">
    <list fill class="bg-theme-background">
        <list-section header="Switch team">
            @foreach ($this->teamOptions as $id => $name)
                <list-item
                    ref="team-{{ $id }}"
                    :headline="$name"
                    :trailingIconIos="$name === $activeTeamName ? Ios::Checkmark : null"
                    :trailingIconAndroid="$name === $activeTeamName ? Android::Check : null"
                    :trailingIconColor="theme('primary')"
                    @tap="selectTeam({{ $id }})"
                />
            @endforeach
        </list-section>
    </list>
</native:bottom-sheet>
