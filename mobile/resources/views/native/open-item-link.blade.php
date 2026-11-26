@use('App\Enums\ItemType')
@use('App\Http\Integrations\Sunny\SunnySyncCoordinator')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Inventory" back />

@if ($deleted !== null)
    <column ref="item-link-deleted" fill center class="gap-3 bg-theme-background px-6">
        <column class="h-[72] w-[72] items-center justify-center rounded-full bg-theme-primary/15">
            <icon :ios="Ios::Trash" :android="Android::DeleteOutline" :size="32" class="text-theme-primary-text" />
        </column>
        <x-ui.heading size="lg" class="text-center">{{ $deleted['name'] }} was deleted</x-ui.heading>
        <x-ui.text class="text-center">It’s waiting in Recently deleted. You can restore it there, or delete it forever.</x-ui.text>

        @php($place = $deleted['place'] === null ? null : ItemType::from($deleted['place']['type']))
        <column class="w-full gap-3 pt-3">
            <row ref="item-link-deleted-place" class="{{ Card::classes() }} w-full items-center gap-4 px-4 py-3">
                @if ($place !== null)
                    <x-ui.avatar :color="$place->color()" :ios="$place->iosIcon()" :android="$place->androidIcon()" />
                @else
                    <x-ui.avatar :ios="Ios::House" :android="Android::Home" />
                @endif
                <column class="flex-1 gap-0.5">
                    <x-ui.text size="sm">Where it was</x-ui.text>
                    <x-ui.heading size="lg">{{ $deleted['place']['name'] ?? 'Not in a place yet' }}</x-ui.heading>
                </column>
            </row>

            <x-ui.button ref="item-link-open-deleted" class="w-full" tap="openRecentlyDeleted">Open Recently deleted</x-ui.button>
        </column>
    </column>
@else
    <column ref="item-link-missing" fill center class="gap-4 bg-theme-background px-6">
        <text class="text-center text-base text-theme-on-surface-variant">
            This thing isn’t on this phone. It may be in a team you’re not part of, or it’s new. {{ SunnySyncCoordinator::REFRESH_ADVICE }}
        </text>
        @if ($error)
            <text ref="item-link-error" class="text-center text-sm text-theme-destructive">{{ $error }}</text>
        @endif
        <x-ui.button ref="item-link-open-browser" variant="text" tap="openInBrowser">
            Open in browser
        </x-ui.button>
    </column>
@endif
