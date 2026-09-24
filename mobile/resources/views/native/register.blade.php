<native:top-bar title="Create account" back />

<scroll-view ref="register-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        <text ref="register-subtitle" class="text-base text-theme-on-surface-variant">
            Create your account on the Sunny website, then return here to log in.
        </text>
        @if ($error)
            <text class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif
        <button ref="register-submit" variant="primary" size="lg" font="semibold" class="w-full" @tap="register">
            Create account
        </button>
        <button ref="register-login-link" variant="ghost" font="semibold" @navigate.replace='/login'>
            Log in
        </button>
    </column>
</scroll-view>
