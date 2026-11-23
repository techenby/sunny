@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Create account" back />

<scroll-view ref="register-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        <text ref="register-subtitle" class="text-base text-theme-on-surface-variant">
            Enter your details below to create your account.
        </text>

        <column class="w-full gap-4">
            <outlined-text-input
                ref="register-name"
                label="Full name"
                placeholder="Full name"
                autocapitalize="words"
                :ios-leading-icon="Ios::Person"
                :android-leading-icon="Android::Person"
                :disabled="$saving"
                :error="isset($fieldErrors['name'])"
                :supporting="$fieldErrors['name'] ?? null"
                native:model="name"
            />
            <column class="w-full gap-1">
                <outlined-text-input
                    ref="register-email"
                    label="Email address"
                    placeholder="email@example.com"
                    keyboard="email"
                    content-type="username"
                    :ios-leading-icon="Ios::Envelope"
                    :android-leading-icon="Android::Email"
                    :disabled="$saving"
                    :error="isset($fieldErrors['email'])"
                    :supporting="$fieldErrors['email'] ?? null"
                    native:model="email"
                />
                @if ($emailTaken)
                    <row class="w-full justify-start">
                        <x-ui.button
                            ref="register-login-instead"
                            variant="text"
                            a11y-hint="Opens the login screen with this email"
                            tap="showLogin"
                        >
                            Log in instead
                        </x-ui.button>
                    </row>
                @endif
            </column>
            <column class="w-full gap-1.5">
                <outlined-text-input
                    ref="register-password"
                    label="Password"
                    placeholder="Password"
                    secure
                    revealable
                    content-type="new-password"
                    :ios-leading-icon="Ios::Lock"
                    :android-leading-icon="Android::Lock"
                    :disabled="$saving"
                    :error="isset($fieldErrors['password'])"
                    :supporting="$fieldErrors['password'] ?? null"
                    native:model="password"
                />
                <text ref="register-password-rule" class="text-sm text-theme-on-surface-variant">
                    At least 12 characters, with upper and lower case letters, a number and a symbol. Passwords found in known data leaks can’t be used.
                </text>
            </column>
            <outlined-text-input
                ref="register-password-confirmation"
                label="Confirm password"
                placeholder="Confirm password"
                secure
                revealable
                content-type="new-password"
                :ios-leading-icon="Ios::Lock"
                :android-leading-icon="Android::Lock"
                :disabled="$saving"
                :error="isset($fieldErrors['password_confirmation'])"
                :supporting="$fieldErrors['password_confirmation'] ?? null"
                native:model="passwordConfirmation"
            />
        </column>

        <text ref="register-consent" class="text-sm text-theme-on-surface-variant">By creating an account, you agree to our <text ref="register-terms" font="semibold" class="text-theme-primary-text" a11y-hint="Opens the Terms of Service" @tap="openTerms">Terms of Service</text> and <text ref="register-privacy" font="semibold" class="text-theme-primary-text" a11y-hint="Opens the Privacy Policy" @tap="openPrivacyPolicy">Privacy Policy</text>.</text>

        @if ($failure === 'offline')
            <x-ui.alert ref="register-offline" :ios="Ios::WifiSlash" :android="Android::WifiOff" title="You’re offline" message="Creating an account needs a connection. Nothing’s been sent yet." />
        @elseif ($failure === 'server')
            <x-ui.alert ref="register-server-error" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Something went wrong on our end" message="No account was created. Try again in a moment." />
        @elseif ($failure === 'throttled' && $this->throttled())
            <column class="w-full" native:poll="5s">
                <x-ui.alert ref="register-throttled" :ios="Ios::Clock" :android="Android::Schedule" title="Too many tries" message="Wait a minute and try again." />
            </column>
        @elseif ($failure === 'unavailable')
            <x-ui.alert ref="register-unavailable" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Creating an account isn’t available right now" message="No account was created. Try again later." />
        @endif

        @if ($error !== '')
            <text ref="register-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        @php($retry = in_array($failure, ['offline', 'server'], true))
        <x-ui.button
            ref="register-submit"
            class="w-full"
            :ios="$retry && ! $saving ? Ios::ArrowClockwise : null"
            :android="$retry && ! $saving ? Android::Refresh : null"
            :disabled="$saving || $this->throttled()"
            tap="submit"
        >
            {{ $saving ? 'Creating account…' : ($retry ? 'Try again' : 'Create account') }}
        </x-ui.button>

        <row class="w-full items-center justify-center gap-1">
            <text class="text-sm text-theme-on-surface-variant">Already have an account?</text>
            <x-ui.button
                ref="register-login-link"
                variant="text"
                a11y-hint="Opens the login screen"
                :disabled="$saving"
                tap="showLogin"
            >
                Log in
            </x-ui.button>
        </row>
    </column>
</scroll-view>
