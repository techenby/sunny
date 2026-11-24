@use('Native\Mobile\Facades\System')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Forgot password" back />

<scroll-view ref="forgot-password-screen" fill class="bg-theme-background">
    @if ($sentTo === '')
        <column class="w-full gap-6 px-6 py-6">
            <text ref="forgot-password-subtitle" class="text-base text-theme-on-surface-variant">
                We’ll email a link to choose a new password.
            </text>

            <outlined-text-input
                ref="forgot-password-email"
                label="Email address"
                placeholder="email@example.com"
                keyboard="email"
                content-type="email"
                :disabled="$sending"
                :error="$emailError !== ''"
                :supporting="$emailError !== '' ? $emailError : null"
                native:model="email"
            />

            @if ($this->disconnected || $failure === 'offline')
                <x-ui.offline ref="forgot-password-offline" :extra="$this->disconnected ? null : 'Nothing’s been sent yet.'" />
            @elseif ($failure === 'server')
                <x-ui.alert ref="forgot-password-server-error" tone="error" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Something went wrong on our end" message="No link sent. Try again in a moment." />
            @elseif ($failure === 'throttled' && $this->throttled())
                <column class="w-full" native:poll="5s">
                    <x-ui.alert ref="forgot-password-throttled" tone="error" :ios="Ios::Clock" :android="Android::Schedule" title="Too many tries" message="Wait a minute and try again. In the meantime, check your inbox for the links we sent." />
                </column>
            @elseif ($failure === 'unavailable')
                <x-ui.alert ref="forgot-password-unavailable" tone="error" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Password reset isn’t available right now" message="No link sent. Try again later." />
            @endif

            @php($offline = $failure === 'offline' && ! $this->disconnected)
            <x-ui.button
                ref="forgot-password-submit"
                class="w-full"
                :ios="$offline && ! $sending ? Ios::ArrowClockwise : null"
                :android="$offline && ! $sending ? Android::Refresh : null"
                :disabled="$sending || $this->throttled() || $this->disconnected"
                tap="send"
            >
                {{ $sending ? 'Sending link…' : ($offline ? 'Try again' : 'Send link') }}
            </x-ui.button>

            <row class="w-full items-center justify-center gap-1">
                <text class="text-sm text-theme-on-surface-variant">Remember your password?</text>
                <x-ui.button
                    ref="forgot-password-login-link"
                    variant="text"
                    a11y-hint="Goes back to the login screen"
                    tap="backToLogin"
                >
                    Log in
                </x-ui.button>
            </row>
        </column>
    @else
        <column class="w-full items-center gap-6 px-6 py-8">
            <column class="h-[72] w-[72] items-center justify-center rounded-full bg-theme-primary/15">
                <icon :ios="Ios::Envelope" :android="Android::Email" :size="32" class="text-theme-primary-text" />
            </column>

            <column class="w-full items-center gap-2">
                <text ref="forgot-password-sent-title" font="accent" class="text-2xl text-center text-theme-on-background">Check your email</text>
                <text ref="forgot-password-sent-to" font="semibold" class="text-base text-center text-theme-on-background">{{ $sentTo }}</text>
                <text ref="forgot-password-sent-message" class="text-base text-center text-theme-on-surface-variant">{{ $sentMessage }}</text>
            </column>

            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                <row class="w-full items-start gap-3">
                    <icon :ios="Ios::Globe" :android="Android::Language" :size="20" class="text-theme-primary-text" />
                    <text class="flex-1 text-sm text-theme-on-surface-variant">The link opens the website, where you can set your new password.</text>
                </row>
                <row class="w-full items-start gap-3">
                    <icon :ios="Ios::Tray" :android="Android::Inbox" :size="20" class="text-theme-primary-text" />
                    <text class="flex-1 text-sm text-theme-on-surface-variant">Not there in a few minutes? Check your spam folder.</text>
                </row>
            </column>

            <column class="w-full gap-3">
                <x-ui.button ref="forgot-password-back" variant="secondary" class="w-full" tap="backToLogin">
                    Back to log in
                </x-ui.button>
                @if (System::isIos())
                    <x-ui.button
                        ref="forgot-password-open-mail"
                        class="w-full"
                        :ios="Ios::EnvelopeOpen"
                        a11y-hint="Opens the Mail app"
                        tap="openMail"
                    >
                        Open Mail
                    </x-ui.button>
                @endif
            </column>
        </column>
    @endif
</scroll-view>
