@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('Illuminate\Support\Str')

{{-- Shared by the create and edit screens. Expects $formRef, $submitLabel,
     and $tagRows; the remaining state comes from the including component's
     ManagesRecipeForm properties. Computed values have to be passed in
     because an @include is not bound to $this. --}}

<scroll-view ref="{{ $formRef }}-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
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
                native:model.blur="source"
            />

            @include('native.photo-field', [
                'photoPath' => $photoPath,
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
                placeholder="One ingredient per line"
                multiline
                :min-lines="5"
                native:model.blur="ingredients"
            />
            <outlined-text-input
                ref="recipe-instructions"
                label="Instructions"
                placeholder="One step per line"
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

        <button
            ref="{{ $formRef }}-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            @tap="save"
        >
            {{ $submitLabel }}
        </button>
    </column>
</scroll-view>
