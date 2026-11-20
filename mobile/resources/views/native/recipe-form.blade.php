@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')
@use('Illuminate\Support\Str')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     $tagRows and $importCard, plus an optional $remixOf; the remaining state comes from the including component's
     ManagesRecipeForm properties. Computed values have to be passed in
     because an @include is not bound to $this. --}}

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        @if ($remixOf ?? null)
            <pressable
                ref="recipe-remix-of"
                @navigate('/recipes/'.$remixOf['id'])
                :a11y-label="'Remix of '.$remixOf['name'].'. The original stays as it is.'"
                a11y-hint="Opens the original recipe"
                class="{{ Card::classes() }} w-full px-3 py-3"
            >
                <row class="w-full items-center gap-3">
                    <column class="h-11 w-11 items-center justify-center rounded-[12] bg-theme-primary/15">
                        <icon :ios="Ios::DocOnDoc" :android="Android::ContentCopy" :size="20" class="text-theme-primary" />
                    </column>
                    <column class="flex-1 gap-0.5">
                        <text font="medium" class="text-xs text-theme-on-surface-variant">Remix of</text>
                        <x-ui.heading>{{ $remixOf['name'] }}</x-ui.heading>
                        <x-ui.text size="sm">The original stays as it is.</x-ui.text>
                    </column>
                    <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                </row>
            </pressable>
        @endif

        <column class="w-full gap-4">
            <outlined-text-input
                ref="recipe-name"
                label="Name"
                placeholder="Buttermilk Pancakes"
                autocapitalize="words"
                :ios-leading-icon="Ios::Tag"
                :android-leading-icon="Android::Label"
                native:model.blur="name"
            />
            <outlined-text-input
                ref="recipe-source"
                label="Source (URL or text)"
                placeholder="https://example.com/recipe"
                keyboard="url"
                :ios-leading-icon="Ios::Link"
                :android-leading-icon="Android::Link"
                :error="in_array($importCard['state'] ?? null, ['no-recipe', 'unreachable'], true)"
                :disabled="$importing ?? false"
                native:model.debounce.500ms="source"
            />

            @if ($importCard)
                @include('native.recipe-import-card', ['card' => $importCard])
            @endif

            @include('native.photo-field', [
                'photoPath' => $photoPath ?? $existingPhotoUrl,
                'refPrefix' => $formRef,
                'alt' => 'The photo chosen for this recipe',
            ])

            <column class="w-full gap-2">
                <text class="text-sm font-semibold text-theme-on-surface-variant">Tags</text>
                @foreach ($tagRows as $tagRow)
                    <row class="w-full items-center justify-start gap-2">
                        @foreach ($tagRow as $tag)
                            <chip
                                ref="recipe-tag-{{ Str::slug($tag) }}"
                                :label="$tag"
                                :selected="in_array($tag, $tags, true)"
                                @change="toggleTag('{{ $tag }}')"
                            />
                        @endforeach
                    </row>
                @endforeach
            </column>
        </column>

        <column class="w-full gap-4">
            <text class="text-base font-bold text-theme-on-surface">Time &amp; servings</text>

            <outlined-text-input
                ref="recipe-servings"
                label="Servings"
                placeholder="e.g., 4 people"
                native:model.blur="servings"
            />
            <outlined-text-input
                ref="recipe-prep-time"
                label="Prep time"
                placeholder="e.g., 30 min"
                native:model.blur="prepTime"
            />
            <outlined-text-input
                ref="recipe-cook-time"
                label="Cook time"
                placeholder="e.g., 1 hour"
                native:model.blur="cookTime"
            />
            <outlined-text-input
                ref="recipe-total-time"
                label="Total time"
                placeholder="e.g., 1 hour 30 min"
                native:model.blur="totalTime"
            />
        </column>

        <column class="w-full gap-4">
            <text class="text-base font-bold text-theme-on-surface">Method</text>

            <outlined-text-input
                ref="recipe-description"
                label="Description"
                placeholder="Brief description of the recipe..."
                multiline
                :min-lines="3"
                native:model.blur="description"
            />
            <outlined-text-input
                ref="recipe-ingredients"
                label="Ingredients"
                placeholder="One ingredient per line. Start a line with # for a heading."
                multiline
                :min-lines="5"
                native:model.blur="ingredients"
            />
            <outlined-text-input
                ref="recipe-instructions"
                label="Instructions"
                placeholder="One step per line. Start a line with # for a heading."
                multiline
                :min-lines="5"
                native:model.blur="instructions"
            />
            <outlined-text-input
                ref="recipe-notes"
                label="Notes"
                placeholder="Additional notes, tips, or variations..."
                multiline
                :min-lines="4"
                native:model.blur="notes"
            />
            <outlined-text-input
                ref="recipe-nutrition"
                label="Nutrition"
                placeholder="Nutritional information..."
                multiline
                :min-lines="4"
                native:model.blur="nutrition"
            />
        </column>

        @if ($error !== '')
            <text ref="{{ $formRef }}-error" class="text-sm text-theme-destructive">
                {{ $error }}
            </text>
        @endif

        <x-ui.button
            ref="{{ $formRef }}-submit"
            class="w-full"
            :disabled="$saving || $savedId !== null || ($importing ?? false)"
            tap="save"
        >
            {{ $submitLabel }}
        </x-ui.button>
    </column>
</scroll-view>
