@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="New team" back />

<x-ui.submit-bar
    ref="create-team-submit"
    :ios="$offline && ! $this->disconnected ? Ios::ArrowClockwise : null"
    :android="$offline && ! $this->disconnected ? Android::Refresh : null"
    :disabled="$creating || $this->disconnected"
    tap="create"
>
    {{ $offline && ! $this->disconnected ? 'Try again' : 'Create team' }}
</x-ui.submit-bar>

<scroll-view ref="create-team-screen" fill class="bg-theme-background ios:bg-theme-grouped-background">
    <column class="w-full gap-6 px-4 py-6">
        <outlined-text-input
            ref="create-team-name"
            label="Name"
            placeholder="Lake House"
            autocapitalize="words"
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

        @if ($offline || $this->disconnected)
            <x-ui.offline ref="create-team-offline" />
        @endif

        @if ($error !== '')
            <text ref="create-team-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif
    </column>
</scroll-view>
