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
                label="Name"
                placeholder="Full name"
                autocapitalize="words"
                :ios-leading-icon="Ios::Person"
                :android-leading-icon="Android::Person"
                native:model.blur="name"
            />
            <outlined-text-input
                ref="register-email"
                label="Email address"
                placeholder="email@example.com"
                keyboard="email"
                :ios-leading-icon="Ios::Envelope"
                :android-leading-icon="Android::Email"
                native:model.blur="email"
            />
            <outlined-text-input
                ref="register-password"
                label="Password"
                placeholder="Password"
                secure
                :ios-leading-icon="Ios::Lock"
                :android-leading-icon="Android::Lock"
                native:model.blur="password"
            />
            <outlined-text-input
                ref="register-password-confirmation"
                label="Confirm password"
                placeholder="Confirm password"
                secure
                :ios-leading-icon="Ios::Lock"
                :android-leading-icon="Android::Lock"
                native:model.blur="passwordConfirmation"
            />
        </column>

        <button
            ref="register-submit"
            variant="primary"
            size="lg"
            font="semibold"
            class="w-full"
            @navigate.replace='/dashboard'
        >
            Create account
        </button>

        <row class="w-full items-center justify-center gap-1">
            <text class="text-sm text-theme-on-surface-variant">Already have an account?</text>
            <button
                ref="register-login-link"
                variant="ghost"
                font="semibold"
                a11y-hint="Opens the login screen"
                @navigate.replace='/login'
            >
                Log in
            </button>
        </row>
    </column>
</scroll-view>
