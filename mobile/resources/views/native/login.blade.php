@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Log in" display-mode="large" back />

<scroll-view ref="login-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        <text ref="login-subtitle" class="text-base text-theme-on-surface-variant">
            Enter your email and password below to log in.
        </text>

        <column class="w-full gap-4">
            <outlined-text-input
                ref="login-email"
                label="Email address"
                placeholder="email@example.com"
                keyboard="email"
                :ios-leading-icon="Ios::Envelope"
                :android-leading-icon="Android::Email"
                native:model.blur="email"
            />
            <outlined-text-input
                ref="login-password"
                label="Password"
                placeholder="Password"
                secure
                :ios-leading-icon="Ios::Lock"
                :android-leading-icon="Android::Lock"
                native:model.blur="password"
            />
            <checkbox ref="login-remember" label="Remember me" native:model="remember" />
        </column>

        <button ref="login-submit" variant="primary" size="lg" font="semibold" class="w-full">
            Log in
        </button>

        <row class="w-full items-center justify-center gap-1">
            <text class="text-sm text-theme-on-surface-variant">Don’t have an account?</text>
            <button
                ref="login-register-link"
                variant="ghost"
                font="semibold"
                a11y-hint="Opens the registration screen"
                @navigate.replace='/register'
            >
                Sign up
            </button>
        </row>
    </column>
</scroll-view>
