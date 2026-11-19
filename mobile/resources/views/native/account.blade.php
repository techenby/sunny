@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar :title="$verificationRequired ? 'Verify your email' : 'Account'" :back="! $verificationRequired" />

<scroll-view ref="account-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        @if ($verificationRequired)
            <x-ui.text>Verify your email to sync and use your Sunny household. If the address is incorrect, update it below.</x-ui.text>
        @endif

        @unless ($verified)
            <column ref="account-verify" class="{{ Card::classes() }} w-full gap-3 p-4">
                <row class="w-full items-start gap-3">
                    <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                        <icon :ios="Ios::Envelope" :android="Android::Email" :size="18" class="text-theme-primary-text" />
                    </column>
                    <column class="flex-1 gap-1">
                        <x-ui.badge size="sm" class="self-start">Not verified</x-ui.badge>
                        <text font="semibold" class="text-base text-theme-on-surface">{{ $emailChanged ? 'Verify your new email' : 'Verify your email' }}</text>
                        <x-ui.text size="sm">
                            {{ $verificationSent ? 'We sent a link to '.$savedEmail.'.' : 'Check '.$savedEmail.' for a verification link, or request a new one.' }}
                        </x-ui.text>
                        <x-ui.text size="sm">Open the link and sign in on the web if asked.</x-ui.text>
                        @if ($verificationRequired)
                            <x-ui.text size="sm">Then return here and tap Check verification.</x-ui.text>
                        @endif
                    </column>
                </row>
                <button
                    ref="account-send-verification"
                    variant="secondary"
                    size="lg"
                    font="semibold"
                    class="w-full"
                    @tap="sendVerification"
                >
                    {{ $verificationSent ? 'Send it again' : 'Send verification email' }}
                </button>
                <x-ui.text size="sm" class="text-center">Didn’t get it? You can resend a few times a minute.</x-ui.text>
            </column>
        @endunless

        @if ($verificationRequired || ! $verified)
            <button ref="account-check-verification" variant="secondary" size="lg" class="w-full" @tap="checkVerification">
                {{ $verified ? 'Continue to Sunny' : 'Check verification' }}
            </button>
        @endif

        @if ($offline)
            <row ref="account-offline" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
                <native:icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="20" class="text-theme-primary-text" />
                <column class="flex-1 gap-0.5">
                    <text font="semibold" class="text-base text-theme-primary-text">You’re offline</text>
                    <text class="text-base text-theme-on-surface">{{ $this->offlineMessage() }}</text>
                </column>
            </row>
        @endif

        @if ($error !== '')
            <text ref="account-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        <column class="w-full gap-4">
            <outlined-text-input
                ref="account-name"
                label="Name"
                autocapitalize="words"
                content-type="name"
                :ios-leading-icon="Ios::Person"
                :android-leading-icon="Android::Person"
                :error="isset($fieldErrors['name'])"
                :supporting="$fieldErrors['name'] ?? null"
                native:model.blur="name"
            />
            <outlined-text-input
                ref="account-email"
                label="Email"
                keyboard="email"
                content-type="email"
                :ios-leading-icon="Ios::Envelope"
                :android-leading-icon="Android::Email"
                :error="isset($fieldErrors['email'])"
                :supporting="$fieldErrors['email'] ?? null"
                native:model.blur="email"
            />
        </column>

        <button
            ref="account-save"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            :ios-icon="$offline === 'save' ? Ios::ArrowClockwise : null"
            :android-icon="$offline === 'save' ? Android::Refresh : null"
            :disabled="$saving"
            @tap="save"
        >
            {{ $offline === 'save' ? 'Try again' : 'Save' }}
        </button>

        @if ($verificationRequired)
            <button ref="verification-account-options" variant="ghost" class="w-full" @navigate="'/preferences'">
                Account options
            </button>
        @endif
    </column>
</scroll-view>
