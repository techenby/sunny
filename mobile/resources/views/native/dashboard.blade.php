@use('Illuminate\Support\Str')
@use('Native\Mobile\Facades\System')
@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Summary" display-mode="large" :back="false">
    <native:top-bar-action id="add" label="Add" :ios-icon="Ios::Plus" :android-icon="Android::Add">
        <native:top-bar-action
            id="add-recipe"
            label="New recipe"
            :ios-icon="Ios::ForkKnife"
            :android-icon="Android::Restaurant"
            url="/recipes/create"
        />
        <native:top-bar-action
            id="add-item"
            label="New item"
            :ios-icon="Ios::Shippingbox"
            :android-icon="Android::Inventory2"
            url="/inventory/create"
        />
    </native:top-bar-action>
    <native:top-bar-action
        id="log-out"
        label="Log out"
        :ios-icon="Ios::RectanglePortraitAndArrowRight"
        :android-icon="Android::Logout"
        @tap="confirmLogOut"
    />
</native:top-bar>

<refreshable ref="dashboard" fill class="bg-theme-background" @refresh="sync">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        @if ($syncError)
            <pressable
                ref="sync-error"
                @tap="sync"
                a11y-label="Download failed. Tap to retry."
                class="w-full rounded-xl bg-theme-destructive/10 px-4 py-3"
            >
                <row class="w-full items-center gap-3">
                    <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="22" class="text-theme-destructive" />
                    <column class="flex-1 gap-0.5">
                        <text font="semibold" class="text-base text-theme-destructive">Download failed</text>
                        <text class="text-sm text-theme-on-surface-variant">{{ $syncError }}</text>
                    </column>
                </row>
            </pressable>
        @endif

        @if ($this->teamOptions)
            <pressable
                ref="active-team"
                @tap="openTeamPicker"
                :a11y-label="'Active team: '.$activeTeamName"
                :press-opacity="0.7"
                class="w-full"
            >
                <row class="w-full items-center gap-3">
                    <column class="h-11 w-11 items-center justify-center rounded-full bg-theme-primary">
                        <text font="accent" class="text-lg text-theme-on-primary">{{ $this->teamInitial }}</text>
                    </column>
                    <column class="flex-1 gap-0.5">
                        <text class="text-sm text-theme-on-surface-variant">Active team</text>
                        <text font="semibold" class="text-lg text-theme-on-background">{{ $activeTeamName }}</text>
                    </column>
                    @if (count($this->teamOptions) > 1)
                        <icon :ios="Ios::ChevronUpChevronDown" :android="Android::UnfoldMore" :size="18" class="text-theme-on-surface-variant" />
                    @endif
                </row>
            </pressable>
        @else
            <column ref="no-teams" class="w-full rounded-xl bg-theme-surface px-4 py-3 gap-0.5">
                <text font="semibold" class="text-base text-theme-on-surface">No teams available</text>
                <text class="text-sm text-theme-on-surface-variant">Pull down to download your teams.</text>
            </column>
        @endif

        <row class="w-full gap-3">
            <pressable
                ref="dashboard-recipes-all"
                @navigate('/recipes')
                a11y-label="Recipes"
                :press-scale="0.97"
                class="flex-1 gap-3 rounded-xl bg-theme-surface p-4 shadow-sm"
            >
                <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary">
                    <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="18" class="text-theme-on-primary" />
                </column>
                <column class="gap-0.5">
                    <text font="accent" class="text-3xl text-theme-on-surface">{{ $this->summary['recipes'] }}</text>
                    <text font="semibold" class="text-base text-theme-on-surface">Recipes</text>
                    <text class="text-sm text-theme-on-surface-variant">Ready to cook</text>
                </column>
            </pressable>
            <pressable
                ref="dashboard-inventory-all"
                @navigate('/inventory')
                a11y-label="Inventory"
                :press-scale="0.97"
                class="flex-1 gap-3 rounded-xl bg-theme-surface p-4 shadow-sm"
            >
                <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-accent">
                    <icon :ios="Ios::Archivebox" :android="Android::Inventory2" :size="18" class="text-theme-on-accent" />
                </column>
                <column class="gap-0.5">
                    <text font="accent" class="text-3xl text-theme-on-surface">{{ $this->summary['items'] }}</text>
                    <text font="semibold" class="text-base text-theme-on-surface">Items</text>
                    <text class="text-sm text-theme-on-surface-variant">
                        {{ $this->summary['locations'] }} {{ Str::plural('location', $this->summary['locations']) }} · {{ $this->summary['bins'] }} {{ Str::plural('bin', $this->summary['bins']) }}
                    </text>
                </column>
            </pressable>
        </row>

        <column class="w-full gap-3">
            <text font="semibold" class="text-xl text-theme-on-background">Recent recipes</text>
            @if ($this->recentRecipes)
                {{-- iOS insets the carousel's content by 16pt, so let it bleed to the screen edges to line up with the heading. --}}
                <carousel ref="recent-recipes" :item-width="180" :item-spacing="12" :class="System::isIos() ? '-mx-4' : null">
                    @foreach ($this->recentRecipes as $recipe)
                        <pressable
                            ref="dashboard-recipes-{{ $recipe['id'] }}"
                            @navigate($recipe['url'])
                            :a11y-label="$recipe['name']"
                            class="w-full h-[210] bg-theme-surface"
                        >
                            <stack class="w-full h-[130]">
                                <column class="w-full h-full items-center justify-center bg-theme-primary/15">
                                    <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="32" class="text-theme-primary" />
                                </column>
                                @if ($recipe['photo'])
                                    <image :src="$recipe['photo']" :alt="'Photo of '.$recipe['name']" class="w-full h-full object-cover" />
                                @endif
                            </stack>
                            <column class="w-full gap-0.5 px-3 py-2">
                                <text font="semibold" max-lines="2" class="text-base text-theme-on-surface">{{ $recipe['name'] }}</text>
                                <text max-lines="1" class="text-sm text-theme-on-surface-variant">{{ $recipe['supporting'] }}</text>
                            </column>
                        </pressable>
                    @endforeach
                </carousel>
            @else
                <pressable
                    ref="recipes-empty"
                    @navigate('/recipes/create')
                    a11y-label="Add your first recipe"
                    class="w-full items-center gap-2 rounded-xl bg-theme-surface px-4 py-6"
                >
                    <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="28" class="text-theme-primary" />
                    <text font="semibold" class="text-base text-theme-on-surface">Add your first recipe</text>
                    <text class="text-sm text-theme-on-surface-variant">Recipes you add or update will show up here.</text>
                </pressable>
            @endif
        </column>

        <column class="w-full gap-3">
            <text font="semibold" class="text-xl text-theme-on-background">Recent items</text>
            @if ($this->recentItems)
                <column class="w-full rounded-xl bg-theme-surface">
                    @foreach ($this->recentItems as $item)
                        <pressable
                            ref="dashboard-inventory-{{ $item['id'] }}"
                            @navigate($item['url'])
                            :a11y-label="$item['name']"
                            :press-opacity="0.7"
                            class="w-full px-4 py-3"
                        >
                            <row class="w-full items-center gap-3">
                                <column class="h-9 w-9 items-center justify-center rounded-full bg-{{ $item['type']->iconColor() }}">
                                    <icon :ios="$item['type']->iosIcon()" :android="$item['type']->androidIcon()" :size="18" class="text-white" />
                                </column>
                                <column class="flex-1 gap-0.5">
                                    <text font="semibold" max-lines="1" class="text-base text-theme-on-surface">{{ $item['name'] }}</text>
                                    <text max-lines="1" class="text-sm text-theme-on-surface-variant">{{ $item['supporting'] }}</text>
                                </column>
                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                            </row>
                        </pressable>
                        @unless ($loop->last)
                            <divider class="ml-16" />
                        @endunless
                    @endforeach
                </column>
            @else
                <pressable
                    ref="items-empty"
                    @navigate('/inventory/create')
                    a11y-label="Add your first item"
                    class="w-full items-center gap-2 rounded-xl bg-theme-surface px-4 py-6"
                >
                    <icon :ios="Ios::Archivebox" :android="Android::Inventory2" :size="28" class="text-theme-accent" />
                    <text font="semibold" class="text-base text-theme-on-surface">Add your first item</text>
                    <text class="text-sm text-theme-on-surface-variant">Items you add or update will show up here.</text>
                </pressable>
            @endif
        </column>
    </column>
</refreshable>

<native:bottom-sheet ref="team-picker" :visible="$showTeamPicker" @dismiss="closeTeamPicker" detents="medium">
    <list fill class="bg-theme-background">
        <list-section header="Switch team">
            @foreach ($this->teamOptions as $id => $name)
                <list-item
                    ref="team-{{ $id }}"
                    :headline="$name"
                    :leadingMonogram="mb_strtoupper(mb_substr($name, 0, 1))"
                    :leadingMonogramColor="theme('primary')"
                    :trailingIconIos="$name === $activeTeamName ? Ios::Checkmark : null"
                    :trailingIconAndroid="$name === $activeTeamName ? Android::Check : null"
                    :trailingIconColor="theme('primary')"
                    @tap="selectTeam({{ $id }})"
                />
            @endforeach
        </list-section>
    </list>
</native:bottom-sheet>
