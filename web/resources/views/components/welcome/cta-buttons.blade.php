<div {{ $attributes->class('flex items-center gap-3') }}>
    @auth
        <flux:button :href="route('dashboard')" variant="primary">{{ __('Open your dashboard') }}</flux:button>
    @else
        <flux:button :href="route('register')" variant="primary">{{ __('Get started') }}</flux:button>
        <flux:button :href="route('login')" variant="ghost">{{ __('Log in') }}</flux:button>
    @endauth
</div>
