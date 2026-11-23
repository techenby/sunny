@use('App\Http\Integrations\Sunny\SunnySyncCoordinator')

<native:top-bar title="Inventory" back />

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
