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
                <row class="w-full justify-end">
                    <x-ui.button
                        ref="login-forgot-password"
                        variant="text"
                        a11y-hint="Opens password reset"
                        tap="forgotPassword"
                    >
                        Forgot password?
                    </x-ui.button>
                </row>
            </column>
        @else
            <text class="text-base text-theme-on-surface-variant">{{ $useRecoveryCode ? 'Enter one of your recovery codes.' : 'Enter the code from your authenticator app.' }}</text>
            <outlined-text-input ref="login-code" :label="$useRecoveryCode ? 'Recovery code' : 'Authentication code'" native:model="code" />
            <x-ui.button ref="login-recovery" variant="text" tap="toggleRecoveryCode">{{ $useRecoveryCode ? 'Use authenticator code' : 'Use recovery code' }}</x-ui.button>
            <x-ui.button ref="login-restart" variant="text" tap="startOver">Restart login</x-ui.button>
        @endif

        @if ($error)
            <text ref="login-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        <x-ui.button
            ref="login-submit"
            class="w-full"
            tap="submit"
        >
            {{ $twoFactor ? 'Verify code' : 'Log in' }}
        </x-ui.button>

        <row class="w-full items-center justify-center gap-1">
            <text class="text-sm text-theme-on-surface-variant">Don’t have an account?</text>
            <x-ui.button
                ref="login-register-link"
                variant="text"
                a11y-hint="Opens the registration screen"
                tap="showRegister"
            >
                Sign up
            </x-ui.button>
        </row>
    </column>
</scroll-view>
