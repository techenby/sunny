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
            <x-ui.button
                ref="recipe-import-dismiss"
                variant="secondary"
                :ios="Ios::Xmark"
                :android="Android::Close"
                a11y-label="Dismiss"
                tap="dismissImported"
            />
        @endif
    </row>

    @if ($card['state'] === 'ready')
        <text class="text-sm text-theme-on-surface-variant">Sunny can read this page and fill in the name, photo, ingredients, steps and times for you.</text>
        <x-ui.button
            ref="recipe-import"
            variant="secondary"
            class="w-full"
            :ios="Ios::ArrowDownToLine"
            :android="Android::Download"
            a11y-label="Import this recipe"
            tap="importRecipe"
        >Import this recipe</x-ui.button>
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
                    <x-ui.button
                        ref="recipe-import-change-link"
                        variant="secondary"
                        class="w-full"
                        :ios="Ios::Pencil"
                        :android="Android::Edit"
                        a11y-hint="Clears Source so you can paste another link"
                        tap="changeImportLink"
                    >Change link</x-ui.button>
                @else
                    <x-ui.button
                        ref="recipe-import-retry"
                        variant="secondary"
                        class="w-full"
                        :ios="Ios::ArrowClockwise"
                        :android="Android::Refresh"
                        tap="importRecipe"
                    >Try again</x-ui.button>
                @endif
                <x-ui.button ref="recipe-import-fill-in" variant="secondary" class="w-full" tap="fillInMyself">Fill it in myself</x-ui.button>
            </column>
        @endif
    @endif
</column>
