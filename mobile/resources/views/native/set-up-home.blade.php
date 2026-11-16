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
        <text ref="setup-summary" class="w-full text-center text-sm text-theme-on-surface-variant">{{ $this->summary }}</text>
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
            <text ref="setup-title" font="bold" class="text-3xl leading-tight text-theme-on-background">
                {{ $this->firstName ? 'What’s in your home, '.$this->firstName.'?' : 'What’s in your home?' }}
            </text>
            <x-ui.text size="lg">Tick what fits and we’ll set it up. You can rename or remove anything later.</x-ui.text>
        </column>

        @if ($error)
            <text ref="setup-error" class="text-base text-theme-destructive">{{ $error }}</text>
        @endif

        <column class="w-full gap-3">
            <x-ui.heading size="lg">Places you keep things</x-ui.heading>
            <row ref="setup-places" class="w-full flex-wrap gap-2">
                @foreach ($this->placeOptions as $index => $place)
                    @php($picked = in_array($place, $places, true))
                    <pressable
                        ref="setup-place-{{ $index }}"
                        :a11y-label="$place.($picked ? ', selected' : '')"
                        class="min-h-12 justify-center rounded-full border px-4 {{ $picked ? 'border-theme-primary bg-theme-primary' : 'border-theme-outline bg-theme-surface' }}"
                        @tap="togglePlace({{ $index }})"
                    >
                        <row class="items-center gap-1.5">
                            @if ($picked)
                                <icon :ios="Ios::Checkmark" :android="Android::Check" :size="14" class="text-theme-on-primary" />
                            @endif
                            <text font="medium" class="text-base {{ $picked ? 'text-theme-on-primary' : 'text-theme-on-surface' }}">{{ $place }}</text>
                        </row>
                    </pressable>
                @endforeach
                @unless ($addingPlace)
                    <pressable
                        ref="setup-own-place"
                        a11y-label="Add your own place"
                        class="min-h-12 justify-center rounded-full border border-dashed border-theme-on-surface-variant/50 px-4"
                        @tap="startOwnPlace"
                    >
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::Plus" :android="Android::Add" :size="14" class="text-theme-on-surface-variant" />
                            <text font="medium" class="text-base text-theme-on-surface-variant">Your own</text>
                        </row>
                    </pressable>
                @endunless
            </row>
            @if ($addingPlace)
                <row class="w-full items-center gap-3">
                    <row class="h-12 flex-1 items-center rounded-full border border-theme-outline bg-theme-surface px-4">
                        <bare-text-input
                            ref="setup-new-place"
                            class="flex-1"
                            placeholder="Like Laundry room"
                            a11y-label="Name your own place"
                            autocapitalize="words"
                            autofocus
                            native:model="newPlace"
                            @submit="addOwnPlace"
                        />
                    </row>
                    <pressable
                        ref="setup-add-place"
                        a11y-label="Add place"
                        class="h-12 w-12 items-center justify-center rounded-full bg-theme-primary"
                        @tap="addOwnPlace"
                    >
                        <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-on-primary" />
                    </pressable>
                </row>
            @endif
        </column>

        <column class="w-full gap-3">
            <row class="w-full items-center gap-3">
                <x-ui.heading size="lg" class="flex-1">A few things to start you off</x-ui.heading>
                <x-ui.text size="sm">Optional</x-ui.text>
            </row>
            <column class="{{ Card::classes() }} w-full">
                @foreach ([
                    ['key' => 'groceries', 'title' => 'Groceries list', 'description' => 'A shopping list for the whole house', 'ios' => Ios::Cart, 'android' => Android::ShoppingCart],
                    ['key' => 'bedtime', 'title' => 'Bedtime routine', 'description' => 'Bath, pajamas, teeth and a story', 'ios' => Ios::Moon, 'android' => Android::Bedtime],
                    ['key' => 'recipe', 'title' => 'A sample recipe', 'description' => 'Apple cider donuts, to try things out', 'ios' => Ios::BookPages, 'android' => Android::MenuBook],
                ] as $starter)
                    @php($picked = $this->{$starter['key']})
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
