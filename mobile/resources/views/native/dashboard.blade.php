@use('Illuminate\Support\Str')
@use('Native\Mobile\Facades\System')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')
@use('Native\Mobile\Edge\Layouts\Builders\NavAction')

<native:top-bar title="Summary" display-mode="large" :back="false">
    <native:top-bar-action
        ref="preferences"
        id="preferences"
        label="Preferences"
        :ios-icon="Ios::Gearshape"
        :android-icon="Android::Settings"
        @navigate('/preferences')
    />
</native:top-bar>

@php
    $addMenu = [
        NavAction::make('add-recipe')->label('New recipe')->icon(ios: Ios::ForkKnife->value, android: Android::Restaurant->value)->url('/recipes/create'),
        NavAction::make('add-item')->label('New item')->icon(ios: Ios::Shippingbox->value, android: Android::Inventory2->value)->url('/inventory/create'),
        NavAction::make('scan-items')->label('Bulk add')->icon(ios: Ios::CameraViewfinder->value, android: Android::DocumentScanner->value)->url('/inventory/scan'),
    ];
@endphp

<native:bottom-bar>
    <row class="w-full items-center justify-end px-4 pb-2">
        <pressable
            ref="dashboard-add"
            a11y-label="Add"
            :menu="$addMenu"
            class="h-12 w-12 items-center justify-center rounded-full glass android:bg-theme-surface"
        >
            <native:icon :ios="Ios::Plus" :android="Android::Add" :size="22" class="text-theme-on-surface" />
        </pressable>
    </row>
</native:bottom-bar>

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
                        <x-ui.heading class="text-theme-destructive">Download failed</x-ui.heading>
                        <x-ui.text size="sm">{{ $syncError }}</x-ui.text>
                    </column>
                </row>
            </pressable>
        @endif

        @unless ($this->teamOptions)
            <column ref="no-teams" class="{{ Card::classes() }} w-full gap-0.5 px-4 py-3">
                <x-ui.heading>No teams available</x-ui.heading>
                <x-ui.text size="sm">Pull down to download your teams.</x-ui.text>
            </column>
        @endunless

        <row class="w-full gap-3">
            <pressable
                ref="dashboard-recipes-all"
                @navigate.replace('/recipes')
                a11y-label="Recipes"
                :press-scale="0.97"
                class="{{ Card::classes() }} flex-1 gap-3 p-4"
            >
                <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary">
                    <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="18" class="text-theme-on-primary" />
                </column>
                <column class="gap-0.5">
                    <text font="accent" class="text-3xl text-theme-on-surface">{{ $this->summary['recipes'] }}</text>
                    <x-ui.heading>Recipes</x-ui.heading>
                    <x-ui.text size="sm">Ready to cook</x-ui.text>
                </column>
            </pressable>
            <pressable
                ref="dashboard-inventory-all"
                @navigate.replace('/inventory')
                a11y-label="Inventory"
                :press-scale="0.97"
                class="{{ Card::classes() }} flex-1 gap-3 p-4"
            >
                <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-accent">
                    <icon :ios="Ios::Archivebox" :android="Android::Inventory2" :size="18" class="text-theme-on-accent" />
                </column>
                <column class="gap-0.5">
                    <text font="accent" class="text-3xl text-theme-on-surface">{{ $this->summary['items'] }}</text>
                    <x-ui.heading>Items</x-ui.heading>
                    <x-ui.text size="sm">
                        {{ $this->summary['locations'] }} {{ Str::plural('place', $this->summary['locations']) }} · {{ $this->summary['bins'] }} {{ Str::plural('storage spot', $this->summary['bins']) }}
                    </x-ui.text>
                </column>
            </pressable>
        </row>

        <column class="w-full gap-3">
            <x-ui.heading size="lg">Recent recipes</x-ui.heading>
            @if ($this->recentRecipes)
                {{-- iOS insets the carousel's content by 16pt, so let it bleed to the screen edges to line up with the heading. --}}
                <carousel ref="recent-recipes" :item-width="180" :item-spacing="12" :class="System::isIos() ? '-mx-4' : null">
                    @foreach ($this->recentRecipes as $recipe)
                        <pressable
                            ref="dashboard-recipes-{{ $recipe['id'] }}"
                            @navigate($recipe['url'])
                            :a11y-label="$recipe['name']"
                            class="{{ Card::classes() }} min-h-[210] w-full"
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
                                <x-ui.heading max-lines="2">{{ $recipe['name'] }}</x-ui.heading>
                                <x-ui.text size="sm" max-lines="2">{{ $recipe['supporting'] }}</x-ui.text>
                            </column>
                        </pressable>
                    @endforeach
                </carousel>
            @else
                <pressable
                    ref="recipes-empty"
                    @navigate('/recipes/create')
                    a11y-label="Add your first recipe"
                    class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6"
                >
                    <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="28" class="text-theme-primary" />
                    <x-ui.heading>Add your first recipe</x-ui.heading>
                    <x-ui.text size="sm">Recipes you add or update will show up here.</x-ui.text>
                </pressable>
            @endif
        </column>

        <column class="w-full gap-3">
            <x-ui.heading size="lg">Recent items</x-ui.heading>
            @if ($this->recentItems)
                <column class="{{ Card::classes() }} w-full">
                    @foreach ($this->recentItems as $item)
                        <pressable
                            ref="dashboard-inventory-{{ $item['id'] }}"
                            @navigate($item['url'])
                            :a11y-label="$item['name']"
                            :press-opacity="0.7"
                            class="w-full px-4 py-3"
                        >
                            <row class="w-full items-center gap-4">
                                <x-ui.avatar
                                    :size="$item['photo'] ? '2xl' : 'md'"
                                    :src="$item['photo']"
                                    :alt="'Photo of '.$item['name']"
                                    :color="$item['type']->color()"
                                    :ios="$item['type']->iosIcon()"
                                    :android="$item['type']->androidIcon()"
                                />
                                <column class="flex-1 gap-0.5">
                                    <x-ui.heading max-lines="1">{{ $item['name'] }}</x-ui.heading>
                                    <x-ui.text size="sm" max-lines="1">{{ $item['supporting'] }}</x-ui.text>
                                </column>
                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                            </row>
                        </pressable>
                        @unless ($loop->last)
                            <divider class="ml-[72]" />
                        @endunless
                    @endforeach
                </column>
            @else
                <pressable
                    ref="items-empty"
                    @navigate('/inventory/create')
                    a11y-label="Add your first item"
                    class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6"
                >
                    <icon :ios="Ios::Archivebox" :android="Android::Inventory2" :size="28" class="text-theme-accent" />
                    <x-ui.heading>Add your first item</x-ui.heading>
                    <x-ui.text size="sm">Items you add or update will show up here.</x-ui.text>
                </pressable>
            @endif
        </column>
    </column>
</refreshable>
