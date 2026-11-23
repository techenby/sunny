@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Account" back />

<x-ui.submit-bar
    ref="account-save"
    :ios="$offline === 'save' && ! $this->disconnected ? Ios::ArrowClockwise : null"
    :android="$offline === 'save' && ! $this->disconnected ? Android::Refresh : null"
    :disabled="$saving || $this->disconnected"
>
    {{ $offline === 'save' && ! $this->disconnected ? 'Try again' : 'Save' }}
</x-ui.submit-bar>

<scroll-view ref="account-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-4 py-6">
        <column class="w-full gap-4">
            <outlined-text-input
                ref="account-name"
                label="Name"
                autocapitalize="words"
                content-type="name"
                :error="isset($fieldErrors['name'])"
                :supporting="$fieldErrors['name'] ?? null"
                native:model.blur="name"
            />
            <outlined-text-input
                ref="account-email"
                label="Email"
                keyboard="email"
                content-type="email"
                :error="isset($fieldErrors['email'])"
                :supporting="$fieldErrors['email'] ?? null"
                native:model.blur="email"
            />
        </column>

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
                            {{ $verificationSent ? 'We sent a link to '.$savedEmail.'. Tap it to confirm it’s yours.' : 'We haven’t sent anything yet. Send a link to '.$savedEmail.' and tap it to confirm it’s yours.' }}
                        </x-ui.text>
                    </column>
                </row>
                <x-ui.button
                    ref="account-send-verification"
                    variant="secondary"
                    class="w-full"
                    :disabled="$this->disconnected"
                    tap="sendVerification"
                >
                    {{ $verificationSent ? 'Send it again' : 'Send verification email' }}
                </x-ui.button>
                <x-ui.text size="sm" class="text-center">Didn’t get it? You can resend a few times a minute.</x-ui.text>
            </column>
        @endunless

        @if ($offline || $this->disconnected)
            <x-ui.offline ref="account-offline" :extra="$this->offlineReassurance()" />
        @endif

        @if ($error !== '')
            <text ref="account-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif
    </column>
</scroll-view>
