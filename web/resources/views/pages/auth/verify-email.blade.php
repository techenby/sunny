<x-layouts::auth>
    @if (\App\Support\KioskTeam::inKioskSession())
        <div class="mt-4 flex flex-col items-center gap-6">
            <flux:text class="text-center">
                {{ __('Verify the email address of the account that paired this kiosk on another device, then reopen the kiosk.') }}
            </flux:text>
            <flux:button :href="route('kiosk.index')">{{ __('Reopen kiosk') }}</flux:button>
        </div>
    @else
        <div class="mt-4 flex flex-col gap-6">
            <flux:text class="text-center">
                {{ __('Verify :email to use Sunny on the web. Open the verification link in your inbox, or request a new one below.', ['email' => auth()->user()->email]) }}
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

                <flux:link :href="route('profile.edit')">{{ __('Change your email address') }}</flux:link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:button>
                </form>
            </div>
        </div>
    @endif
</x-layouts::auth>
