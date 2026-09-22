@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Dashboard">
    <native:top-bar-action
        id="log-out"
        label="Log out"
        :ios-icon="Ios::RectanglePortraitAndArrowRight"
        :android-icon="Android::Logout"
        @tap="logOut"
    />
</native:top-bar>

<scroll-view ref="dashboard-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        <column class="w-full gap-2">
            <text font="mono-bold" class="text-sm uppercase tracking-widest text-theme-primary">
                Welcome home
            </text>
            <text ref="dashboard-title" font="accent" class="text-xl leading-tight text-theme-on-background">
                Your household at a glance
            </text>
        </column>

        <column ref="dashboard-sections" class="w-full gap-3">
            <pressable
                ref="dashboard-recipes"
                a11y-label="Recipes"
                a11y-hint="Opens your recipes"
                class="w-full"
                @navigate='/recipes'
            >
                <native:feature-card
                    key="dashboard-recipes"
                    title="Recipes"
                    description="Save favorites, track ingredients, and remix your own variations."
                    :ios-icon="Ios::BookPages"
                    :android-icon="Android::MenuBook"
                    navigable
                />
            </pressable>
            <pressable
                ref="dashboard-inventory"
                a11y-label="Inventory"
                a11y-hint="Opens your inventory"
                class="w-full"
                @navigate='/inventory'
            >
                <native:feature-card
                    key="dashboard-inventory"
                    title="Inventory"
                    description="Organize your garage, basement, and pantry."
                    :ios-icon="Ios::Archivebox"
                    :android-icon="Android::Inventory2"
                    navigable
                />
            </pressable>
            <native:feature-card
                key="dashboard-teams"
                title="Teams"
                description="Invite family members to collaborate."
                :ios-icon="Ios::Person3"
                :android-icon="Android::Groups"
            />
        </column>
    </column>
</scroll-view>
