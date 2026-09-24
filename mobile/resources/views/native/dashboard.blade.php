@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Dashboard" subtitle="Your household at a glance" :back="false">
    <native:top-bar-action id="sync" label="Sync" :ios-icon="Ios::ArrowClockwise" :android-icon="Android::Sync" @tap="sync" />
    <native:top-bar-action
        id="log-out"
        label="Log out"
        :ios-icon="Ios::RectanglePortraitAndArrowRight"
        :android-icon="Android::Logout"
        @tap="logOut"
    />
</native:top-bar>

<list ref="dashboard" fill class="bg-theme-background">
    <list-section header="Sync">
        <list-item ref="sync-status" :headline="$this->syncStatus" :supporting="$syncError ?: 'Recipes and inventory are stored on this device for offline browsing.'" />
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
            @navigate('/inventory/create')
        />
    </list-section>

    <list-section header="Coming soon">
        <list-item
            ref="dashboard-teams"
            headline="Teams"
            supporting="Invite family members to collaborate."
            :leadingIconIos="Ios::Person3"
            :leadingIconAndroid="Android::Groups"
        />
    </list-section>
</list>
