@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')
@use('App\NativeComponents\Recipes')

@php($recipe = $this->recipe)

<native:top-bar :title="$recipe ? '' : 'Recipe'" back />

@if ($recipe)
    <native:bottom-bar>
        <column class="w-full gap-3 px-4 pb-2">
            <row class="w-full items-center justify-end gap-3">
                @if ($this->canCook())
                    <x-ui.button
                        ref="cook-recipe"
                        class="flex-1"
                        :ios="Ios::Flame"
                        :android="Android::LocalFireDepartment"
                        a11y-label="Cook"
                        :navigate="'/recipes/'.$recipe['id'].'/cook'"
                    >Cook</x-ui.button>
                @else
                    <x-ui.button
                        ref="edit-recipe"
                        variant="glass"
                        class="flex-1"
                        :ios="Ios::Pencil"
                        :android="Android::Edit"
                        a11y-label="Edit"
                        :navigate="'/recipes/'.$recipe['id'].'/edit'"
                    >Edit</x-ui.button>
                @endif
                <x-ui.button
                    ref="recipe-more"
                    variant="glass"
                    :ios="Ios::Ellipsis"
                    :android="Android::MoreHoriz"
                    a11y-label="More"
                    :menu="$this->moreMenu()"
                />
            </row>
        </column>
    </native:bottom-bar>

    <scroll-view ref="recipe-detail" fill class="bg-theme-background">
        <column class="w-full gap-6 px-4 pt-2 pb-8">
            <column class="w-full gap-3">
                @if ($recipe['photo_url'] ?? null)
                    <image
                        ref="recipe-photo"
                        :src="$recipe['photo_url']"
                        :alt="'Photo of '.$recipe['name']"
                        class="w-full h-[240] rounded-[18] object-cover"
                    />
                @else
                    <column class="w-full h-[160] items-center justify-center rounded-[18] bg-theme-primary/15">
                        <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="44" class="text-theme-primary" />
                    </column>
                @endif

                <text ref="recipe-name" font="accent" class="text-3xl text-theme-on-background">{{ $recipe['name'] }}</text>

                @include('native.queued-change', ['refPrefix' => 'recipe', 'queuedChange' => $this->queuedChange])

                @if ($error !== '')
                    <text ref="recipe-error" class="text-base text-theme-destructive">{{ $error }}</text>
                @endif

                @if ($recipe['description'])
                    <text ref="recipe-description" class="text-base text-theme-on-surface-variant">{{ $recipe['description'] }}</text>
                @endif

                @if ($recipe['tags'])
                    <row ref="recipe-tags" class="w-full flex-wrap gap-2">
                        @foreach ($recipe['tags'] as $tag)
                            <x-ui.badge size="sm">{{ $tag }}</x-ui.badge>
                        @endforeach
                    </row>
                @endif
            </column>

            @if ($this->details)
                <column ref="recipe-facts" class="w-full gap-3">
                    @foreach (array_chunk($this->details, 2, true) as $pair)
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

            @if ($recipe['source'])
                @if (Recipes::isSourceUrl($recipe['source']))
                    <pressable
                        ref="recipe-source"
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
                    <column ref="recipe-source" class="{{ Card::classes() }} w-full gap-0.5 px-4 py-3">
                        <x-ui.text size="sm">Source</x-ui.text>
                        <x-ui.heading>{{ $recipe['source'] }}</x-ui.heading>
                    </column>
                @endif
            @endif

            @if ($this->ingredients)
                <column class="w-full gap-3">
                    <row class="w-full items-center">
                        <x-ui.heading size="lg" class="flex-1">Ingredients</x-ui.heading>
                        @if ($this->checkedCount)
                            <x-ui.text size="sm">{{ $this->checkedCount }} of {{ $this->ingredientCount }}</x-ui.text>
                        @endif
                    </row>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($this->ingredients as $index => $ingredient)
                            @if ($this->isHeading($ingredient))
                                <x-ui.heading ref="ingredient-heading-{{ $index }}" class="w-full px-4 pt-4 pb-1">{{ $ingredient['text'] }}</x-ui.heading>
                                @continue
                            @endif
                            @php($checked = $this->isChecked($index))
                            <pressable
                                ref="ingredient-{{ $index }}"
                                @press="toggleIngredient({{ $index }})"
                                :a11y-label="($checked ? 'Checked: ' : '').$ingredient['text']"
                                class="w-full px-4 py-3"
                            >
                                <row class="w-full items-start gap-3">
                                    <icon
                                        :ios="$checked ? Ios::CheckmarkCircleFill : Ios::Circle"
                                        :android="$checked ? Android::CheckCircle : Android::RadioButtonUnchecked"
                                        :size="22"
                                        :class="$checked ? 'text-theme-primary' : 'text-theme-outline'"
                                    />
                                    <text :class="$checked ? 'flex-1 text-base text-theme-on-surface-variant line-through' : 'flex-1 text-base text-theme-on-surface'">{{ $ingredient['text'] }}</text>
                                </row>
                            </pressable>
                            @unless ($loop->last || $this->isHeading($this->ingredients[$index + 1]))
                                <divider class="ml-[50]" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($this->instructions)
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Instructions</x-ui.heading>
                    <column class="{{ Card::classes() }} w-full gap-4 p-4">
                        @foreach ($this->instructions as $instruction)
                            @if ($this->isHeading($instruction))
                                <x-ui.heading ref="step-heading-{{ $loop->index }}" class="w-full">{{ $instruction['text'] }}</x-ui.heading>
                                @continue
                            @endif
                            <row ref="step-{{ $instruction['number'] }}" class="w-full items-start gap-3">
                                <column class="h-7 w-7 items-center justify-center rounded-full bg-theme-on-surface-variant/15">
                                    <text font="semibold" class="text-sm text-theme-on-surface-variant">{{ $instruction['number'] }}</text>
                                </column>
                                <text class="flex-1 text-base text-theme-on-surface">{{ $instruction['text'] }}</text>
                            </row>
                        @endforeach
                    </column>
                </column>
            @endif

            @if (! $this->ingredients && ! $this->instructions)
                <pressable
                    ref="recipe-add-steps"
                    @navigate('/recipes/'.$recipe['id'].'/edit')
                    a11y-label="Add ingredients and steps"
                    class="{{ Card::classes() }} w-full items-center gap-2 px-4 py-6"
                >
                    <icon :ios="Ios::Pencil" :android="Android::Edit" :size="26" class="text-theme-primary" />
                    <x-ui.heading>Add ingredients and steps</x-ui.heading>
                    <x-ui.text size="sm">This recipe doesn’t have any yet.</x-ui.text>
                </pressable>
            @endif

            @if ($recipe['notes'])
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Notes</x-ui.heading>
                    <text ref="recipe-notes" class="{{ Card::classes() }} w-full p-4 text-base text-theme-on-surface">{{ $recipe['notes'] }}</text>
                </column>
            @endif

            @if ($recipe['nutrition'])
                <column class="w-full gap-3">
                    <x-ui.heading size="lg">Nutrition</x-ui.heading>
                    <column ref="recipe-nutrition" class="{{ Card::classes() }} w-full gap-1 p-4">
                        @foreach (preg_split('/\R/', $recipe['nutrition']) as $line)
                            <text class="text-base text-theme-on-surface">{{ $line }}</text>
                        @endforeach
                    </column>
                </column>
            @endif

            @foreach (['Remixed from' => $this->parent ? [$this->parent] : [], 'Remixes' => $this->remixes] as $heading => $relatedRecipes)
                @if ($relatedRecipes)
                    <column class="w-full gap-3">
                        <x-ui.heading size="lg">{{ $heading }}</x-ui.heading>
                        <column class="{{ Card::classes() }} w-full">
                            @foreach ($relatedRecipes as $related)
                                <pressable
                                    ref="related-recipe-{{ $related['id'] }}"
                                    @navigate('/recipes/'.$related['id'])
                                    :a11y-label="$related['name']"
                                    class="w-full px-4 py-3"
                                >
                                    <row class="w-full items-center gap-3">
                                        @if ($related['thumb_path'] ?? $related['photo_url'] ?? null)
                                            <image :src="$related['thumb_path'] ?? $related['photo_url']" :alt="'Photo of '.$related['name']" class="h-9 w-9 rounded-lg object-cover" />
                                        @else
                                            <column class="h-9 w-9 items-center justify-center rounded-lg bg-theme-primary/15">
                                                <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="20" class="text-theme-primary" />
                                            </column>
                                        @endif
                                        <x-ui.heading class="flex-1">{{ $related['name'] }}</x-ui.heading>
                                        <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                                    </row>
                                </pressable>
                                @unless ($loop->last)
                                    <divider class="ml-16" />
                                @endunless
                            @endforeach
                        </column>
                    </column>
                @endif
            @endforeach
        </column>
    </scroll-view>

    <native:bottom-sheet ref="recipe-copy-sheet" :visible="$showCopy" @dismiss="closeCopy" detents="fit">
        @if ($showCopy)
            @php($thumb = $recipe['thumb_url'] ?? $recipe['photo_url'] ?? null)
            @php($copyTeams = $this->copyTeams())
            @php($teamName = $this->teamName())
            <column class="w-full bg-theme-background">
                <x-ui.sheet-header ref="recipe-copy-close" title="Copy to another team" close="closeCopy" />
                <column class="w-full gap-5 px-4 pt-2">
                    <x-ui.text class="px-1">The copy gets everything, tags and photo too. This recipe stays here, just as it is.</x-ui.text>

                    <row ref="recipe-copy-source" class="{{ Card::classes() }} w-full items-center gap-3 px-4 py-3">
                        @if ($thumb)
                            <image :src="$thumb" :alt="'Photo of '.$recipe['name']" class="h-12 w-12 rounded-[10] object-cover" />
                        @else
                            <column class="h-12 w-12 items-center justify-center rounded-[10] bg-theme-primary/15">
                                <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="22" class="text-theme-primary" />
                            </column>
                        @endif
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">{{ $recipe['name'] }}</x-ui.heading>
                            @if ($teamName)
                                <x-ui.text>From {{ $teamName }}</x-ui.text>
                            @endif
                        </column>
                    </row>

                    <column class="w-full gap-2">
                        <x-ui.subheading class="px-1">Copy to</x-ui.subheading>
                        <column ref="recipe-copy-teams" class="{{ Card::classes() }} w-full">
                            @foreach ($copyTeams as $id => $name)
                                <pressable
                                    ref="recipe-copy-team-{{ $id }}"
                                    :a11y-label="$name.($id === $copyTeamId ? ', selected' : '')"
                                    class="min-h-12 w-full justify-center px-4 py-2"
                                    @tap="pickCopyTeam({{ $id }})"
                                >
                                    <row class="w-full items-center gap-4">
                                        <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary">
                                            <text font="medium" class="text-base text-theme-on-primary">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</text>
                                        </column>
                                        <text class="flex-1 text-lg text-theme-on-surface">{{ $name }}</text>
                                        @if ($id === $copyTeamId)
                                            <icon :ios="Ios::Checkmark" :android="Android::Check" :size="18" class="text-theme-primary-text" />
                                        @endif
                                    </row>
                                </pressable>
                                @unless ($loop->last)
                                    <divider class="ml-[68]" />
                                @endunless
                            @endforeach
                        </column>
                    </column>

                    @if ($copyProblem !== null)
                        @php($note = $this->copyNote())
                        <x-ui.note
                            ref="recipe-copy-problem"
                            :tone="match ($copyProblem) { 'offline' => 'offline', 'unsent' => 'info', default => 'error' }"
                            :ios="$copyProblem === 'offline' ? Ios::WifiSlash : Ios::ExclamationmarkCircle"
                            :android="$copyProblem === 'offline' ? Android::WifiOff : Android::ErrorOutline"
                            :title="$note['title']"
                            :message="$note['message']"
                        />
                    @endif

                </column>

                <x-ui.sheet-confirm
                    ref="recipe-copy-submit"
                    :ios="$copyProblem === null ? Ios::SquareOnSquare : Ios::ArrowClockwise"
                    :android="$copyProblem === null ? Android::FileCopy : Android::Refresh"
                    tap="copyToTeam"
                >
                    {{ $copyProblem === null ? 'Copy to '.($copyTeams[$copyTeamId] ?? 'another team') : 'Try again' }}
                </x-ui.sheet-confirm>
            </column>
        @endif
    </native:bottom-sheet>

    <native:bottom-sheet ref="recipe-share-sheet" :visible="$showShare" @dismiss="closeShare" detents="fit">
        @if ($showShare)
            @php($thumb = $recipe['thumb_url'] ?? $recipe['photo_url'] ?? null)
            @php($shareUrl = $this->shareUrl())
            @php([$shareIos, $shareAndroid, $shareTap, $shareLabel] = match (true) {
                $shareProblem !== null => [Ios::ArrowClockwise, Android::Refresh, 'retryShare', 'Try again'],
                (bool) $shareUrl => [Ios::SquareAndArrowUp, Android::Share, 'sendLink', 'Send link'],
                default => [Ios::Link, Android::Link, 'turnOnLink', 'Turn on public link'],
            })
            <column class="w-full bg-theme-background">
                <x-ui.sheet-header ref="recipe-share-close" title="Share recipe" close="closeShare" />
                <column class="w-full gap-5 px-4 pt-2">
                    <x-ui.text class="px-1">{{ $shareUrl ? 'Your link is on. Anyone who has it can open this recipe in a browser.' : 'Turn on a public link and anyone who has it can see this recipe.' }}</x-ui.text>

                    <row ref="recipe-share-source" class="{{ Card::classes() }} w-full items-center gap-3 px-4 py-3">
                        @if ($thumb)
                            <image :src="$thumb" :alt="'Photo of '.$recipe['name']" class="h-12 w-12 rounded-[10] object-cover" />
                        @else
                            <column class="h-12 w-12 items-center justify-center rounded-[10] bg-theme-primary/15">
                                <icon :ios="Ios::ForkKnife" :android="Android::Restaurant" :size="22" class="text-theme-primary" />
                            </column>
                        @endif
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">{{ $recipe['name'] }}</x-ui.heading>
                            <row class="items-center gap-1.5">
                                <icon :ios="$shareUrl ? Ios::Globe : Ios::LockFill" :android="$shareUrl ? Android::Public : Android::Lock" :size="14" class="text-theme-on-surface-variant" />
                                <x-ui.text>{{ $shareUrl ? 'Anyone with the link can see it' : 'Only your home can see it' }}</x-ui.text>
                            </row>
                        </column>
                    </row>

                    @if ($shareUrl)
                        <column ref="recipe-share-link" class="{{ Card::classes() }} w-full">
                            <row class="w-full items-center gap-3 px-4 py-3">
                                <column class="flex-1 gap-0.5">
                                    <x-ui.text size="sm">Public link</x-ui.text>
                                    <text ref="recipe-share-url" class="text-base text-theme-on-surface">{{ preg_replace('~^https?://~', '', $shareUrl) }}</text>
                                </column>
                                <x-ui.button
                                    ref="recipe-share-copy"
                                    variant="secondary"
                                    :ios="$linkCopied ? Ios::Checkmark : Ios::DocOnDoc"
                                    :android="$linkCopied ? Android::Check : Android::ContentCopy"
                                    :a11y-label="$linkCopied ? 'Link copied' : 'Copy link'"
                                    tap="copyLink"
                                >{{ $linkCopied ? 'Copied' : 'Copy' }}</x-ui.button>
                            </row>
                            <divider class="ml-4" />
                            <pressable ref="recipe-share-turn-off" a11y-label="Turn off link" class="min-h-12 w-full justify-center px-4 py-2" @tap="turnOffLink">
                                <row class="w-full items-center gap-3">
                                    <icon :ios="Ios::EyeSlash" :android="Android::LinkOff" :size="18" class="text-theme-destructive" />
                                    <column class="flex-1 gap-0.5">
                                        <text font="semibold" class="text-base text-theme-destructive">Turn off link</text>
                                        <x-ui.text size="sm">The old link stops working right away</x-ui.text>
                                    </column>
                                </row>
                            </pressable>
                        </column>
                    @else
                        <column ref="recipe-share-about" class="{{ Card::classes() }} w-full gap-4 p-4">
                            <row class="w-full items-start gap-3">
                                <icon :ios="Ios::Globe" :android="Android::Public" :size="18" class="text-theme-primary" />
                                <column class="flex-1 gap-0.5">
                                    <text class="text-base text-theme-on-surface">Opens in any browser</text>
                                    <x-ui.text size="sm">No account needed to view it</x-ui.text>
                                </column>
                            </row>
                            <row class="w-full items-start gap-3">
                                <icon :ios="Ios::EyeSlash" :android="Android::LinkOff" :size="18" class="text-theme-primary" />
                                <column class="flex-1 gap-0.5">
                                    <text class="text-base text-theme-on-surface">Turn it off any time</text>
                                    <x-ui.text size="sm">The old link stops working right away</x-ui.text>
                                </column>
                            </row>
                        </column>
                    @endif

                    @if ($shareProblem !== null)
                        @php($note = $this->shareNote())
                        <x-ui.note
                            ref="recipe-share-problem"
                            :tone="match ($shareProblem) { 'offline' => 'offline', 'unsent' => 'info', default => 'error' }"
                            :ios="$shareProblem === 'offline' ? Ios::WifiSlash : Ios::ExclamationmarkCircle"
                            :android="$shareProblem === 'offline' ? Android::WifiOff : Android::ErrorOutline"
                            :title="$note['title']"
                            :message="$note['message']"
                        />
                    @endif

                </column>

                <x-ui.sheet-confirm ref="recipe-share-submit" :ios="$shareIos" :android="$shareAndroid" :tap="$shareTap">{{ $shareLabel }}</x-ui.sheet-confirm>
            </column>
        @endif
    </native:bottom-sheet>
@else
    <x-ui.not-found ref="recipe-missing" thing="recipe" />
@endif
