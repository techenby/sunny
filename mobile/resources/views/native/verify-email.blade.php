@use('Native\Mobile\Facades\System')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

@php
$offline = $this->disconnected;
$busy = $sending || $checking;
@endphp

<native:top-bar title="Verify your email" />

<column ref="verify-email-screen" fill class="bg-theme-background">
    <scroll-view class="w-full flex-1">
        <column class="w-full gap-4 px-6 pt-6 pb-4">
            @if ($offline || $problem === 'offline')
                <x-ui.offline ref="verify-email-offline" />
            @endif

            <x-ui.text ref="verify-email-intro">Verify your email address to start using your household.</x-ui.text>

            <row ref="verify-email-card" class="{{ Card::classes() }} w-full items-center gap-3 px-4 py-3">
                <column class="h-10 w-10 items-center justify-center rounded-full bg-theme-primary/15">
                    <icon :ios="Ios::Envelope" :android="Android::Email" :size="20" class="text-theme-primary-text" />
                </column>
                <column class="flex-1 gap-0.5">
                    <text ref="verify-email-address" class="text-base text-theme-on-surface">{{ $email }}</text>
                    <x-ui.text ref="verify-email-status" size="sm">{{ $sent ? 'Email sent just now' : 'Not verified yet' }}</x-ui.text>
                </column>
                <x-ui.button ref="verify-email-change" variant="text" :a11y-label="'Change '.$email" :disabled="$offline" tap="openChange">Change</x-ui.button>
            </row>

            <x-ui.text ref="verify-email-help" size="sm">
                {{ $sent
                    ? 'Check your inbox for a link from us. It opens in your browser, which may ask you to log in. When you come back, this screen moves on by itself.'
                    : 'We’ll email you a link. It opens in your browser, which may ask you to log in. When you come back, this screen moves on by itself.' }}
            </x-ui.text>

            @if ($problem === 'not-verified')
                <x-ui.alert ref="verify-email-not-verified" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Not verified yet" :message="'Open the link we sent to '.$email.', then try again.'" />
            @elseif ($problem === 'throttled')
                <x-ui.alert ref="verify-email-throttled" :ios="Ios::Clock" :android="Android::Schedule" title="Too many tries" message="Wait a minute and try again." />
            @elseif ($problem === 'send-failed')
                <x-ui.alert ref="verify-email-send-failed" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Something went wrong on our end" message="No email sent. Try again in a moment." />
            @elseif ($problem === 'check-failed')
                <x-ui.alert ref="verify-email-check-failed" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Something went wrong on our end" message="Couldn’t check just now. Try again in a moment." />
            @elseif ($problem === 'locked')
                <x-ui.alert ref="verify-email-locked" :ios="Ios::Lock" :android="Android::Lock" title="Unable to read your saved login" message="Unlock your device and try again." />
            @endif
        </column>
    </scroll-view>

    <column ref="verify-email-actions" class="w-full gap-3 px-6 pt-2 pb-6">
        @if ($sent)
            <row class="w-full items-center justify-center gap-1">
                <x-ui.text size="sm">Didn’t get it?</x-ui.text>
                <x-ui.button ref="verify-email-send-again" variant="text" :disabled="$offline || $busy" :loading="$sending" tap="send">Send again</x-ui.button>
            </row>

            @if (System::isIos())
                <x-ui.button ref="verify-email-open-mail" class="w-full" :ios="Ios::EnvelopeOpen" a11y-hint="Opens the Mail app" :disabled="$offline" tap="openMail">Open Mail</x-ui.button>
                <x-ui.button ref="verify-email-check" variant="secondary" class="w-full" :disabled="$offline || $busy" :loading="$checking" tap="check">I’ve verified</x-ui.button>
            @else
                <x-ui.button ref="verify-email-check" class="w-full" :disabled="$offline || $busy" :loading="$checking" tap="check">I’ve verified</x-ui.button>
            @endif
        @else
            <x-ui.button ref="verify-email-send" class="w-full" :disabled="$offline || $busy" :loading="$sending" tap="send">Send verification email</x-ui.button>
            <x-ui.button ref="verify-email-check" variant="secondary" class="w-full" :disabled="$offline || $busy" :loading="$checking" tap="check">I’ve verified</x-ui.button>
        @endif

        <x-ui.button ref="verify-email-log-out" variant="text" class="self-center" tap="confirmLogOut">Log out</x-ui.button>
    </column>
</column>

<native:bottom-sheet ref="verify-email-change-sheet" :visible="$changing" @dismiss="closeChange" detents="fit">
    @if ($changing)
        <column class="w-full bg-theme-background">
            <x-ui.sheet-header ref="verify-email-change-close" title="Change email" close="closeChange" />
            <column class="w-full gap-2 px-4 pt-4">
                <outlined-text-input
                    ref="verify-email-new-email"
                    label="Email"
                    keyboard="email"
                    content-type="email"
                    :error="$newEmailError !== ''"
                    :supporting="$newEmailError !== '' ? $newEmailError : null"
                    native:model="newEmail"
                />
                <x-ui.field-help>We’ll send a new verification link to this address.</x-ui.field-help>

                @if ($changeProblem === 'offline')
                    <x-ui.offline ref="verify-email-change-offline" :extra="'Your email hasn’t been changed.'" />
                @elseif ($changeProblem === 'throttled')
                    <x-ui.alert ref="verify-email-change-throttled" :ios="Ios::Clock" :android="Android::Schedule" title="Too many tries" message="Wait a minute and try again." />
                @elseif ($changeProblem === 'failed')
                    <x-ui.alert ref="verify-email-change-failed" :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" title="Something went wrong on our end" message="Your email hasn’t been changed. Try again in a moment." />
                @elseif ($changeProblem === 'locked')
                    <x-ui.alert ref="verify-email-change-locked" :ios="Ios::Lock" :android="Android::Lock" title="Unable to read your saved login" message="Unlock your device and try again." />
                @endif
            </column>
            <x-ui.sheet-confirm ref="verify-email-change-save" :loading="$savingEmail" tap="saveEmail">Save</x-ui.sheet-confirm>
        </column>
    @endif
</native:bottom-sheet>
