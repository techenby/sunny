@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="" :back="false">
    <native:top-bar-action
        ref="setup-skip"
        id="setup-skip"
        label="Skip"
        show-label
        @tap="skip"
    />
</native:top-bar>

<native:bottom-bar>
    <column class="w-full gap-2 px-4 pb-2">
        <text ref="setup-summary" max-lines="1" class="w-full text-center text-sm text-theme-on-surface-variant">{{ $this->summary }}</text>
        <button
            ref="setup-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            a11y-hint="Creates what you picked, then opens Sunny"
            :disabled="$saving"
            @tap="setUp"
        >
            {{ $saving ? 'Setting up…' : 'Set up my home' }}
        </button>
    </column>
</native:bottom-bar>

<scroll-view ref="setup-screen" fill class="bg-theme-background">
    <column class="w-full gap-7 px-6 pt-2 pb-8">
        <column class="w-full gap-3">
            <text ref="setup-title" font="accent" class="text-3xl leading-tight text-theme-on-background">
                {{ $this->firstName ? 'What’s in your home, '.$this->firstName.'?' : 'What’s in your home?' }}
            </text>
            <x-ui.text size="lg">Tick what fits and we’ll set it up. You can rename or remove anything later.</x-ui.text>
        </column>

        @if ($error)
            <text ref="setup-error" class="text-base text-theme-destructive">{{ $error }}</text>
        @endif

        <column class="w-full gap-3">
            <x-ui.heading size="lg">Places you keep things</x-ui.heading>
            <column ref="setup-places" class="{{ Card::classes() }} w-full">
                @foreach ($this->placeOptions as $index => $place)
                    @php($picked = in_array($place, $places, true))
                    <pressable
                        ref="setup-place-{{ $index }}"
                        :a11y-label="$place.($picked ? ', selected' : '')"
                        class="min-h-12 w-full justify-center px-4 py-2"
                        @tap="togglePlace({{ $index }})"
                    >
                        <row class="w-full items-center gap-4">
                            <x-ui.avatar :color="ItemType::Location->color()" :ios="ItemType::Location->iosIcon()" :android="ItemType::Location->androidIcon()" />
                            <x-ui.heading size="lg" class="flex-1">{{ $place }}</x-ui.heading>
                            @if ($picked)
                                <column class="h-6 w-6 items-center justify-center rounded-full bg-theme-primary">
                                    <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
                                </column>
                            @else
                                <column class="h-6 w-6 rounded-full border-2 border-theme-outline" />
                            @endif
                        </row>
                    </pressable>
                    <divider class="ml-[72]" />
                @endforeach
                @if ($addingPlace)
                    <row class="min-h-12 w-full items-center gap-4 px-4 py-2">
                        <x-ui.avatar :ios="Ios::Plus" :android="Android::Add" />
                        <bare-text-input
                            ref="setup-new-place"
                            class="flex-1 text-lg"
                            placeholder="Like Laundry room"
                            a11y-label="Name your own place"
                            autocapitalize="words"
                            autofocus
                            native:model="newPlace"
                            @submit="addOwnPlace"
                        />
                        <pressable
                            ref="setup-add-place"
                            a11y-label="Add place"
                            class="h-10 w-10 items-center justify-center rounded-full bg-theme-primary"
                            @tap="addOwnPlace"
                        >
                            <icon :ios="Ios::Checkmark" :android="Android::Check" :size="18" class="text-theme-on-primary" />
                        </pressable>
                    </row>
                @else
                    <pressable
                        ref="setup-own-place"
                        a11y-label="Add your own place"
                        class="min-h-12 w-full justify-center px-4 py-2"
                        @tap="startOwnPlace"
                    >
                        <row class="w-full items-center gap-4">
                            <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                                <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary-text" />
                            </column>
                            <text font="semibold" class="flex-1 text-lg text-theme-primary-text">Your own</text>
                        </row>
                    </pressable>
                @endif
            </column>
        </column>

        <column class="w-full gap-3">
            <row class="w-full items-center gap-3">
                <x-ui.heading size="lg" class="flex-1">A few things to start you off</x-ui.heading>
                <x-ui.text size="sm">Optional</x-ui.text>
            </row>
            <column class="{{ Card::classes() }} w-full">
                @foreach ([
                    ['key' => 'groceries', 'title' => 'Groceries list', 'description' => 'Shared, with a few staples', 'ios' => Ios::Cart, 'android' => Android::ShoppingCart],
                    ['key' => 'bedtime', 'title' => 'Bedtime routine', 'description' => 'Every evening', 'ios' => Ios::Moon, 'android' => Android::Bedtime],
                    ['key' => 'morning', 'title' => 'Morning routine', 'description' => 'Weekday mornings', 'ios' => Ios::Sunrise, 'android' => Android::WbTwilight],
                    ['key' => 'recipe', 'title' => 'A sample recipe', 'description' => 'Apple cider donuts', 'ios' => Ios::BookPages, 'android' => Android::MenuBook],
                ] as $starter)
                    @php($picked = in_array($starter['key'], $starters, true))
                    <pressable
                        ref="setup-starter-{{ $starter['key'] }}"
                        :a11y-label="$starter['title'].($picked ? ', selected' : '')"
                        :a11y-hint="$starter['description']"
                        class="min-h-12 w-full justify-center px-4 py-2"
                        @tap="toggleStarter('{{ $starter['key'] }}')"
                    >
                        <row class="w-full items-center gap-4">
                            <x-ui.avatar circle color="red" :ios="$starter['ios']" :android="$starter['android']" />
                            <column class="flex-1 gap-0.5">
                                <x-ui.heading size="lg">{{ $starter['title'] }}</x-ui.heading>
                                <x-ui.text size="sm">{{ $starter['description'] }}</x-ui.text>
                            </column>
                            @if ($picked)
                                <column class="h-6 w-6 items-center justify-center rounded-full bg-theme-primary">
                                    <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
                                </column>
                            @else
                                <column class="h-6 w-6 rounded-full border-2 border-theme-outline" />
                            @endif
                        </row>
                    </pressable>
                    @unless ($loop->last)
                        <divider class="ml-[72]" />
                    @endunless
                @endforeach
            </column>
        </column>
    </column>
</scroll-view>
