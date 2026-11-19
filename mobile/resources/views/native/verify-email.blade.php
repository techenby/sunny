@use('App\Icons\Android')
@use('App\Icons\Ios')

<column
    ref="verify-email-screen"
    fill
    class="safe-area bg-theme-background px-6 py-6"
>
    <column ref="verify-email-introduction" class="w-full flex-1 justify-center gap-4">
        <column class="h-14 w-14 items-center justify-center rounded-full bg-theme-primary/15">
            <icon :ios="Ios::EnvelopeBadge" :android="Android::MarkEmailRead" :size="26" class="text-theme-primary-text" />
        </column>
        <text ref="verify-email-title" font="accent" class="text-xl leading-tight text-theme-on-background">
            Verify your email
        </text>
        <text ref="verify-email-body" class="text-base leading-relaxed text-theme-on-surface-variant">
            Confirm {{ $email }} is yours to keep using Sunny. Open the link we emailed you, signing in on the web if asked, then come back here.
        </text>
        <text class="text-base leading-relaxed text-theme-on-surface-variant">
            Changes you made on this phone are saved and will sync once you’re verified.
        </text>

        @if ($error !== '')
            <text ref="verify-email-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif
    </column>

    <column ref="verify-email-actions" class="w-full gap-3 pt-6">
        <button
            ref="verify-email-check"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            :disabled="$checking"
            @tap="check"
        >
            I’ve verified my email
        </button>
        <button
            ref="verify-email-send"
            variant="secondary"
            size="lg"
            font="semibold"
            class="w-full"
            @tap="send"
        >
            {{ $sent ? 'Send it again' : 'Send verification email' }}
        </button>
        <row class="w-full items-center justify-between">
            <button ref="verify-email-change" variant="ghost" @navigate('/preferences/account')>Change email</button>
            <button ref="verify-email-log-out" variant="ghost" @tap="confirmLogOut">Log out</button>
        </row>
    </column>
</column>
