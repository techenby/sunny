@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')
@use('Native\Mobile\Facades\System')

@php
$notice = match ($card['state']) {
    'no-recipe' => ['title' => 'No recipe on that page', 'ios' => Ios::ExclamationmarkCircle, 'android' => Android::ErrorOutline, 'tint' => 'text-theme-destructive'],
    'unreachable' => ['title' => 'Couldn’t open that page', 'ios' => Ios::ExclamationmarkCircle, 'android' => Android::ErrorOutline, 'tint' => 'text-theme-destructive'],
    'offline' => ['title' => 'You’re offline', 'ios' => Ios::WifiSlash, 'android' => Android::WifiOff, 'tint' => 'text-theme-primary-text',
        'message' => 'Importing needs Sunny to read the page, so it can’t wait and sync later like your other changes. Your link stays here, so connect and try again.'],
    'error' => ['title' => 'Couldn’t import', 'ios' => Ios::ExclamationmarkCircle, 'android' => Android::ErrorOutline, 'tint' => 'text-theme-destructive'],
    'imported' => ['title' => 'Imported and saved', 'ios' => Ios::Checkmark, 'android' => Android::Check, 'tint' => 'text-theme-primary-text',
        'message' => 'Sunny filled this in from the page. Have a look over it and fix anything that didn’t come across right.'],
    default => null,
};
@endphp

<column ref="recipe-import-card" class="{{ Card::classes() }} w-full gap-3 p-3.5">
    <row class="w-full items-center gap-3">
        <column class="min-h-10 min-w-10 items-center justify-center rounded-lg bg-theme-primary/15">
            <text font="accent" class="text-lg text-theme-primary-text">{{ $card['initial'] }}</text>
        </column>
        <column class="flex-1 gap-0.5">
            <text font="accent" max-lines="1" class="text-base text-theme-on-surface">{{ $card['host'] }}</text>
            @if ($card['path'] !== '')
                <text max-lines="1" class="text-sm text-theme-on-surface-variant">{{ $card['path'] }}</text>
            @endif
        </column>
        @if ($card['state'] === 'imported')
            <pressable
                ref="recipe-import-dismiss"
                a11y-label="Dismiss"
                class="h-12 w-12 items-center justify-center rounded-full bg-theme-surface-variant"
                @tap="dismissImported"
            >
                <icon :ios="Ios::Xmark" :android="Android::Close" :size="16" class="text-theme-on-surface-variant" />
            </pressable>
        @endif
    </row>

    @if ($card['state'] === 'ready')
        <text class="text-sm text-theme-on-surface-variant">Sunny can read this page and fill in the name, photo, ingredients, steps and times for you.</text>
        <pressable ref="recipe-import" a11y-label="Import this recipe" class="min-h-12 w-full items-center justify-center rounded-full bg-theme-primary px-4" @tap="importRecipe">
            <row class="items-center gap-2">
                <icon :ios="Ios::ArrowDownToLine" :android="Android::Download" :size="18" class="text-theme-on-primary" />
                <text font="semibold" class="text-base text-theme-on-primary">Import this recipe</text>
            </row>
        </pressable>
    @elseif ($card['state'] === 'importing')
        @unless (System::isIos())
            <progress-bar ref="recipe-import-progress" indeterminate class="w-full" a11y-label="Importing" />
        @endunless
        <text class="text-sm text-theme-on-surface-variant">Reading the recipe from {{ $card['host'] }}… this takes a few seconds.</text>
        <row ref="recipe-import" a11y-label="Importing" class="min-h-12 w-full items-center justify-center gap-2 rounded-full bg-theme-primary/15 px-4">
            <activity-indicator size="sm" />
            <text font="semibold" class="text-base text-theme-primary-text">Importing</text>
        </row>
    @elseif ($notice)
        <divider />
        <row class="w-full items-start gap-3">
            <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                <icon :ios="$notice['ios']" :android="$notice['android']" :size="18" class="{{ $notice['tint'] }}" />
            </column>
            <column class="flex-1 gap-1">
                <text ref="recipe-import-title" font="semibold" class="text-base text-theme-on-surface">{{ $notice['title'] }}</text>
                <text ref="recipe-import-message" class="text-sm text-theme-on-surface-variant">{{ $notice['message'] ?? $card['message'] }}</text>
            </column>
        </row>

        @if ($card['state'] !== 'imported')
            <column class="w-full gap-2">
                @if (in_array($card['state'], ['no-recipe', 'unreachable'], true))
                    <pressable ref="recipe-import-change-link" a11y-hint="Clears Source so you can paste another link" class="min-h-12 w-full items-center justify-center rounded-full bg-theme-primary px-4" @tap="changeImportLink">
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::Pencil" :android="Android::Edit" :size="16" class="text-theme-on-primary" />
                            <text font="semibold" class="text-base text-theme-on-primary">Change link</text>
                        </row>
                    </pressable>
                @else
                    <pressable ref="recipe-import-retry" class="min-h-12 w-full items-center justify-center rounded-full bg-theme-primary px-4" @tap="importRecipe">
                        <row class="items-center gap-1.5">
                            <icon :ios="Ios::ArrowClockwise" :android="Android::Refresh" :size="16" class="text-theme-on-primary" />
                            <text font="semibold" class="text-base text-theme-on-primary">Try again</text>
                        </row>
                    </pressable>
                @endif
                <pressable ref="recipe-import-fill-in" class="min-h-12 w-full items-center justify-center rounded-full border border-theme-outline bg-theme-surface px-4" @tap="fillInMyself">
                    <text font="semibold" class="text-base text-theme-on-surface">Fill it in myself</text>
                </pressable>
            </column>
        @endif
    @endif
</column>
