@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Change password" back />

<scroll-view ref="change-password-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        <column class="w-full gap-4">
            <outlined-text-input
                ref="current-password"
                label="Current password"
                secure
                revealable
                content-type="password"
                :ios-leading-icon="Ios::Lock"
                :android-leading-icon="Android::Lock"
                :error="isset($fieldErrors['current_password'])"
                :supporting="$fieldErrors['current_password'] ?? null"
                native:model="currentPassword"
            />
            <outlined-text-input
                ref="new-password"
                label="New password"
                secure
                revealable
                content-type="new-password"
                :ios-leading-icon="Ios::Lock"
                :android-leading-icon="Android::Lock"
                :error="isset($fieldErrors['password'])"
                :supporting="$fieldErrors['password'] ?? null"
                native:model="password"
            />
            <outlined-text-input
                ref="confirm-password"
                label="Confirm new password"
                secure
                revealable
                content-type="new-password"
                :ios-leading-icon="Ios::Lock"
                :android-leading-icon="Android::Lock"
                :error="isset($fieldErrors['password_confirmation'])"
                :supporting="$fieldErrors['password_confirmation'] ?? null"
                native:model="passwordConfirmation"
            />
        </column>

        @if ($offline)
            <row ref="change-password-offline" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
                <native:icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="20" class="text-theme-primary-text" />
                <column class="flex-1 gap-0.5">
                    <text font="semibold" class="text-base text-theme-primary-text">You’re offline</text>
                    <text class="text-base text-theme-on-surface">Changing your password needs a connection. Nothing’s been changed yet.</text>
                </column>
            </row>
        @endif

        @if ($error !== '')
            <text ref="change-password-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        <button
            ref="change-password-save"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            :ios-icon="$offline ? Ios::ArrowClockwise : null"
            :android-icon="$offline ? Android::Refresh : null"
            :disabled="$saving"
            @tap="save"
        >
            {{ $offline ? 'Try again' : 'Save password' }}
        </button>
    </column>
</scroll-view>
