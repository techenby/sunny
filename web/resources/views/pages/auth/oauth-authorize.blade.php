<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Connect :client to Sunny', ['client' => $client->name])"
            :description="__('This app is asking to use Sunny on your behalf.')"
        />

        <flux:callout icon="information-circle">
            <flux:callout.text>
                {{ __(':client will be able to see and change your recipes, inventory, calendars, lists, routines, and team settings, acting as you on your current team.', ['client' => $client->name]) }}
            </flux:callout.text>
        </flux:callout>

        <flux:text class="text-center">{{ __('Signed in as :email', ['email' => $user->email]) }}</flux:text>

        <div class="flex flex-col gap-3">
            <form method="POST" action="{{ route('passport.authorizations.approve') }}">
                @csrf
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                <flux:button variant="primary" type="submit" class="w-full" data-test="approve-button">
                    {{ __('Allow access') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="">
                <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                <flux:button type="submit" class="w-full" data-test="deny-button">
                    {{ __('Cancel') }}
                </flux:button>
            </form>
        </div>

        <flux:text class="text-center">{{ __('You can disconnect it at any time from your API token settings.') }}</flux:text>
    </div>
</x-layouts::auth>
