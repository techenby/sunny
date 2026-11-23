@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="New team" back />

<scroll-view ref="create-team-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-6 py-6">
        <outlined-text-input
            ref="create-team-name"
            label="Name"
            placeholder="Lake House"
            autocapitalize="words"
            :ios-leading-icon="Ios::House"
            :android-leading-icon="Android::Home"
            :error="$nameError !== ''"
            :supporting="$nameError !== '' ? $nameError : 'A home, a cabin, or anyone you share things with.'"
            native:model="name"
        />

        <column class="{{ Card::classes() }} w-full">
            <row class="w-full items-start gap-3 p-4">
                <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                    <icon :ios="Ios::Crown" :android="Android::WorkspacePremium" :size="18" class="text-theme-primary-text" />
                </column>
                <column class="flex-1 gap-0.5">
                    <x-ui.heading>You’ll be the owner</x-ui.heading>
                    <x-ui.text size="sm">You can invite family and give them roles once it’s made.</x-ui.text>
                </column>
            </row>
            <divider class="ml-16" />
            <row class="w-full items-start gap-3 p-4">
                <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary/15">
                    <icon :ios="Ios::ArrowLeftArrowRight" :android="Android::SwapHoriz" :size="18" class="text-theme-primary-text" />
                </column>
                <column class="flex-1 gap-0.5">
                    <x-ui.heading>We’ll switch you to it</x-ui.heading>
                    <x-ui.text size="sm">Your other teams stay just as they are in Settings.</x-ui.text>
                </column>
            </row>
        </column>

        @if ($offline)
            <row ref="create-team-offline" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
                <native:icon :ios="Ios::WifiSlash" :android="Android::WifiOff" :size="20" class="text-theme-primary-text" />
                <column class="flex-1 gap-0.5">
                    <text font="semibold" class="text-base text-theme-primary-text">You’re offline</text>
                    <text class="text-base text-theme-on-surface">Creating a team needs a connection. Nothing’s been created yet.</text>
                </column>
            </row>
        @endif

        @if ($error !== '')
            <text ref="create-team-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        <x-ui.button
            ref="create-team-submit"
            class="w-full"
            :ios="$offline ? Ios::ArrowClockwise : null"
            :android="$offline ? Android::Refresh : null"
            :disabled="$creating"
            tap="create"
        >
            {{ $offline ? 'Try again' : 'Create team' }}
        </x-ui.button>
    </column>
</scroll-view>
