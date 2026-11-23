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

        @if ($offline || $this->disconnected)
            <x-ui.offline ref="change-password-offline" :extra="$this->disconnected ? null : 'Nothing’s been changed yet.'" />
        @endif

        @if ($error !== '')
            <text ref="change-password-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        <x-ui.button
            ref="change-password-save"
            class="w-full"
            :ios="$offline && ! $this->disconnected ? Ios::ArrowClockwise : null"
            :android="$offline && ! $this->disconnected ? Android::Refresh : null"
            :disabled="$saving || $this->disconnected"
            tap="save"
        >
            {{ $offline && ! $this->disconnected ? 'Try again' : 'Save' }}
        </x-ui.button>
    </column>
</scroll-view>
