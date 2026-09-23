@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar :title="$twoFactor ? 'Two-factor authentication' : 'Log in'" back />

<scroll-view ref="login-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        @if (! $twoFactor)
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
                    native:model="email"
                />
                <outlined-text-input
                    ref="login-password"
                    label="Password"
                    placeholder="Password"
                    secure
                    :ios-leading-icon="Ios::Lock"
                    :android-leading-icon="Android::Lock"
                    native:model="password"
                />
            </column>
        @else
            <text class="text-base text-theme-on-surface-variant">{{ $useRecoveryCode ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.' }}</text>
            <outlined-text-input ref="login-code" :label="$useRecoveryCode ? 'Recovery code' : 'Authentication code'" native:model="code" />
            <button ref="login-recovery" variant="ghost" @tap="toggleRecoveryCode">{{ $useRecoveryCode ? 'Use authenticator code' : 'Use recovery code' }}</button>
            <button ref="login-restart" variant="ghost" @tap="startOver">Restart login</button>
        @endif

        @if ($error)
            <text ref="login-error" class="text-sm text-theme-error">{{ $error }}</text>
        @endif

        <button
            ref="login-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            @tap="submit"
        >
            {{ $twoFactor ? 'Verify code' : 'Log in' }}
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
