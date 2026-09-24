@use('App\Icons\Android')
@use('App\Icons\Ios')

<column
    ref="welcome-screen"
    fill
    class="safe-area bg-theme-background px-6 py-6"
>
    <row ref="sunny-brand" class="w-full items-center gap-3">
        <image
            ref="sunny-brand-icon"
            src="{{ public_path('images/sunny-icon.png') }}"
            alt="Sunny"
            class="h-11 w-11 rounded-xl object-contain shadow-sm"
        />
        <text font="accent" class="text-lg text-theme-on-background">
            Sunny
        </text>
    </row>

    <column ref="sunny-introduction" class="w-full flex-1 justify-center">
        <text font="mono-bold" class="mb-3 text-sm uppercase tracking-widest text-theme-primary">
            Welcome home
        </text>
        <text ref="welcome-title" font="accent" class="text-xl leading-tight text-theme-on-background">
            Your household, organized.
        </text>
        <text ref="welcome-subtitle" class="mt-3 text-base leading-relaxed text-theme-on-surface-variant">
            Sunny helps your family collaborate on recipes and keep track of what’s in storage, all in one place.
        </text>

        <column ref="feature-list" class="mt-8 w-full gap-3">
            <native:feature-card
                key="feature-recipes"
                title="Recipes"
                description="Save, share, and remix family favorites."
                :ios-icon="Ios::BookPages"
                :android-icon="Android::MenuBook"
            />
            <native:feature-card
                key="feature-inventory"
                title="Inventory"
                description="Know what you have and where it lives."
                :ios-icon="Ios::Archivebox"
                :android-icon="Android::Inventory2"
            />
            <native:feature-card
                key="feature-teams"
                title="Teams"
                description="Share recipes and inventory across your household."
                :ios-icon="Ios::Person3"
                :android-icon="Android::Groups"
            />
        </column>
    </column>

    @if ($error)
        <text ref="session-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        <button ref="session-retry" variant="ghost" @tap="restoreSession">Retry</button>
    @endif

    <column ref="auth-actions" class="w-full gap-3 pt-6">
        <button
            ref="register-button"
            variant="primary"
            size="lg"
            font="semibold"
            a11y-label="Register"
            a11y-hint="Opens the registration screen"
            class="w-full"
            @navigate='/register'
        >
            Register
        </button>
        <button
            ref="login-button"
            variant="secondary"
            size="lg"
            font="semibold"
            a11y-label="Log in"
            a11y-hint="Opens the login screen"
            class="w-full"
            @navigate='/login'
        >
            Log in
        </button>
    </column>
</column>
