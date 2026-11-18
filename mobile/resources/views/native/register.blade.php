<native:top-bar title="Create account" back />

<scroll-view ref="register-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        <text ref="register-subtitle" class="text-base text-theme-on-surface-variant">
            Sign up on the Sunny website without leaving the app. When you’re done, close it and log in here.
        </text>
        @if ($error)
            <text class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif
        <button ref="register-submit" variant="primary" size="lg" font="semibold" class="w-full" @tap="register">
            Create account
        </button>
        <button ref="register-login-link" variant="ghost" font="semibold" @tap="showLogin">
            Log in
        </button>
    </column>
</scroll-view>
