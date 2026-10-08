<native:top-bar title="Item" back />

<column ref="item-link-missing" fill center class="gap-4 bg-theme-background px-6">
    <text class="text-center text-base text-theme-on-surface-variant">
        This item isn’t on this phone. It may be in a team you’re not part of, or it hasn’t synced yet.
    </text>
    @if ($error)
        <text ref="item-link-error" class="text-center text-sm text-theme-destructive">{{ $error }}</text>
    @endif
    <button ref="item-link-open-browser" variant="ghost" font="semibold" @tap="openInBrowser">
        Open in browser
    </button>
</column>
