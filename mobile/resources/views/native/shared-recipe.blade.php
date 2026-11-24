@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Support\Offline')
@use('App\Ui\Card')
@use('App\NativeComponents\Recipes')

<native:top-bar title="" back />

@if ($state === 'found' && $recipe)
    @php
        $teamName = $this->teamName();
        $ingredients = $this->lines('ingredients');
        $instructions = $this->lines('instructions');
    @endphp

    <native:bottom-bar>
        <column class="w-full gap-3 px-4 pb-2">
            @if ($savedId !== null)
                <row ref="shared-recipe-saved" class="{{ Card::classes() }} w-full items-center gap-3 py-3 pl-4 pr-3">
                    <column class="h-8 w-8 items-center justify-center rounded-full bg-theme-primary">
                        <native:icon :ios="Ios::Checkmark" :android="Android::Check" :size="16" class="text-theme-on-primary" />
                    </column>
                    <column class="flex-1 gap-0.5">
                        <text font="semibold" class="text-base text-theme-on-surface">{{ $alreadySaved ? 'Already in your recipes' : 'Saved to your recipes' }}</text>
                        @if ($teamName)
                            <x-ui.text size="sm">In {{ $teamName }}</x-ui.text>
                        @endif
                    </column>
                    <x-ui.button ref="shared-recipe-open" a11y-label="Open your copy" tap="openSaved">Open</x-ui.button>
                </row>
            @else
                @if ($saveProblem !== null)
                    @php
                        $note = $this->saveNote();
                    @endphp
                    <x-ui.alert
                        ref="shared-recipe-problem"
                        :tone="match ($saveProblem) { 'offline' => 'offline', 'unsent' => 'info', default => 'error' }"
                        :ios="$saveProblem === 'offline' ? Ios::WifiSlash : Ios::ExclamationmarkCircle"
                        :android="$saveProblem === 'offline' ? Android::WifiOff : Android::ErrorOutline"
                        :title="$note['title']"
                        :message="$note['message']"
                    />
                @endif
                <x-ui.button
                    ref="shared-recipe-save"
                    class="w-full"
                    :ios="$saveProblem === null ? Ios::SquareAndArrowDown : Ios::ArrowClockwise"
                    :android="$saveProblem === null ? Android::Download : Android::Refresh"
                    tap="save"
                >
                    {{ $saveProblem === null ? 'Save to '.($teamName ?? 'my recipes') : 'Try again' }}
                </x-ui.button>
            @endif
        </column>
    </native:bottom-bar>

    <scroll-view ref="shared-recipe" fill class="bg-theme-background">
        <column class="w-full gap-6 px-4 pt-2 pb-8">
            <column class="w-full gap-3">
                @if ($recipe['photo_url'] ?? null)
                    <image
                        ref="shared-recipe-photo"
                        :src="$recipe['photo_url']"
                        :alt="'Photo of '.$recipe['name']"
                        class="w-full h-[240] rounded-[18] object-cover"
                    />
                @else
                    <column class="w-full h-[160] items-center justify-center rounded-[18] bg-theme-primary/15">
                        <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="44" class="text-theme-primary" />
                    </column>
                @endif

                <text ref="shared-recipe-name" font="accent" class="text-3xl text-theme-on-background">{{ $recipe['name'] }}</text>

                @if ($recipe['description'] ?? null)
                    <text ref="shared-recipe-description" class="text-base text-theme-on-surface-variant">{{ $recipe['description'] }}</text>
                @endif

                <row ref="shared-recipe-badge" class="items-center gap-1.5">
                    <icon :ios="Ios::Link" :android="Android::Link" :size="14" class="text-theme-primary" />
                    <x-ui.text size="sm">Shared with you · View only</x-ui.text>
                </row>
            </column>

            @if ($this->details())
                <column ref="shared-recipe-facts" class="w-full gap-3">
                    @foreach (array_chunk($this->details(), 2, true) as $pair)
                        <row class="w-full gap-3">
                            @foreach ($pair as $label => $value)
                                <column class="{{ Card::classes() }} flex-1 gap-0.5 p-4">
                                    <x-ui.text size="sm">{{ $label }}</x-ui.text>
                                    <x-ui.heading>{{ $value }}</x-ui.heading>
                                </column>
                            @endforeach
                            @if (count($pair) === 1)
                                <column class="flex-1" />
                            @endif
                        </row>
                    @endforeach
                </column>
            @endif

            @if ($recipe['source'] ?? null)
                @if (Recipes::isSourceUrl($recipe['source']))
                    <pressable
                        ref="shared-recipe-source"
                        @press="openSource"
                        a11y-label="Open source"
                        a11y-hint="Opens the source in the browser"
                        class="{{ Card::classes() }} w-full px-4 py-3"
                    >
                        <row class="w-full items-center gap-3">
                            <icon :ios="Ios::Link" :android="Android::Link" :size="18" class="text-theme-primary" />
                            <column class="flex-1 gap-0.5">
                                <x-ui.text size="sm">Source</x-ui.text>
                                <x-ui.heading>{{ Recipes::shortenedSource($recipe['source']) }}</x-ui.heading>
                            </column>
                            <icon :ios="Ios::ArrowUpRight" :android="Android::OpenInNew" :size="16" class="text-theme-on-surface-variant" />
                        </row>
                    </pressable>
                @else
                    <column ref="shared-recipe-source" class="{{ Card::classes() }} w-full gap-0.5 px-4 py-3">
                        <x-ui.text size="sm">Source</x-ui.text>
                        <x-ui.heading>{{ $recipe['source'] }}</x-ui.heading>
                    </column>
                @endif
            @endif

            @if ($ingredients)
                <column class="w-full gap-3">
                    <row class="w-full items-center">
                        <x-ui.heading size="lg" class="flex-1">Ingredients</x-ui.heading>
                        @if ($recipe['servings'] ?? null)
                            <x-ui.text size="sm">For {{ $recipe['servings'] }}</x-ui.text>
                        @endif
                    </row>
                    <column ref="shared-recipe-ingredients" class="{{ Card::classes() }} w-full gap-3 p-4">
                        @foreach ($ingredients as $ingredient)
                            @if ($ingredient['type'] === 'heading')
                                <x-ui.heading class="w-full">{{ $ingredient['text'] }}</x-ui.heading>
                                @continue
                            @endif
                            <row class="w-full items-start gap-3">
                                <column class="h-6 w-2 items-center justify-center">
                                    <column class="h-1.5 w-1.5 rounded-full bg-theme-primary" />
                                </column>
                                <text class="flex-1 text-base text-theme-on-surface">{{ $ingredient['text'] }}</text>
                            </row>
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($instructions)
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Instructions</x-ui.heading>
                    <column ref="shared-recipe-instructions" class="{{ Card::classes() }} w-full gap-4 p-4">
                        @foreach ($instructions as $instruction)
                            @if ($instruction['type'] === 'heading')
                                <x-ui.heading class="w-full">{{ $instruction['text'] }}</x-ui.heading>
                                @continue
                            @endif
                            <row class="w-full items-start gap-3">
                                <column class="h-7 w-7 items-center justify-center rounded-full bg-theme-on-surface-variant/15">
                                    <text font="semibold" class="text-sm text-theme-on-surface-variant">{{ $instruction['number'] }}</text>
                                </column>
                                <text class="flex-1 text-base text-theme-on-surface">{{ $instruction['text'] }}</text>
                            </row>
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($recipe['notes'] ?? null)
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Notes</x-ui.heading>
                    <text ref="shared-recipe-notes" class="{{ Card::classes() }} w-full p-4 text-base text-theme-on-surface">{{ $recipe['notes'] }}</text>
                </column>
            @endif

            @if ($recipe['nutrition'] ?? null)
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Nutrition</x-ui.heading>
                    <column ref="shared-recipe-nutrition" class="{{ Card::classes() }} w-full gap-1 p-4">
                        @foreach (preg_split('/\R/', $recipe['nutrition']) as $line)
                            <text class="text-base text-theme-on-surface">{{ $line }}</text>
                        @endforeach
                    </column>
                </column>
            @endif
        </column>
    </scroll-view>
@elseif ($state === 'loading')
    <column ref="shared-recipe-loading" fill center class="gap-3 bg-theme-background px-6">
        <activity-indicator />
        <x-ui.text class="text-center">Opening the recipe…</x-ui.text>
    </column>
@else
    @php
        [$icon, $title, $body] = match ($state) {
            'gone' => [[Ios::EyeSlash, Android::LinkOff], 'This recipe isn’t shared anymore', 'Whoever sent it may have turned sharing off, or the link isn’t quite right. Ask them to send it again.'],
            'offline' => [[Ios::WifiSlash, Android::WifiOff], Offline::TITLE, Offline::ADVICE],
            default => [[Ios::ExclamationmarkCircle, Android::ErrorOutline], 'Couldn’t open this recipe', $message],
        };
    @endphp
    <column ref="shared-recipe-{{ $state }}" fill center class="gap-4 bg-theme-background px-8">
        <column class="h-[72] w-[72] items-center justify-center rounded-full bg-theme-primary/15">
            <icon :ios="$icon[0]" :android="$icon[1]" :size="30" class="text-theme-primary" />
        </column>
        <column class="w-full items-center gap-2">
            <text font="accent" class="text-center text-2xl text-theme-on-background">{{ $title }}</text>
            <x-ui.text class="text-center">{{ $body }}</x-ui.text>
        </column>
        @if ($state === 'gone')
            <x-ui.button ref="shared-recipe-go-to-recipes" tap="goToRecipes">Go to my recipes</x-ui.button>
        @else
            <x-ui.button ref="shared-recipe-retry" :ios="Ios::ArrowClockwise" :android="Android::Refresh" tap="load">Try again</x-ui.button>
        @endif
    </column>
@endif
