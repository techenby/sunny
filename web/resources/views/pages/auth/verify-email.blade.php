<x-layouts::auth>
    <div class="mt-4 flex flex-col gap-6">
        @if (\App\Support\KioskTeam::inKioskSession())
            <flux:text class="text-center">
                {{ __('Verify your email in SunnyHome on your phone or computer, then return to this display.') }}
            </flux:text>

            <flux:button :href="route('kiosk.index')" variant="primary">
                {{ __('Return to kiosk') }}
            </flux:button>
        @else
            <flux:text class="text-center">
                {{ __('Verify your email address to use SunnyHome on the web. Open the verification link for :email, or request a new one below.', ['email' => auth()->user()->email]) }}
            </flux:text>

            @if (session('status') == 'verification-link-sent')
                <flux:text class="text-center font-medium !dark:text-green-400 !text-green-600">
                    {{ __('A new verification link has been sent to your email address.') }}
                </flux:text>
            @endif

            <div class="flex flex-col items-center justify-between space-y-3">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Resend verification email') }}
                    </flux:button>
                </form>

                <flux:link :href="route('profile.edit')">{{ __('Change email address') }}</flux:link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:button>
                </form>
            </div>
        @endif
    </div>
</x-layouts::auth>
