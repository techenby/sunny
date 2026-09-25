<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100">
        {{-- Header --}}
        <header class="flex items-center justify-between px-6 py-4 lg:px-10">
            <div class="flex items-center gap-2">
                <flux:avatar :src="asset('icon.png')" size="sm" />
                <span class="text-lg font-semibold">{{ config('app.name') }}</span>
            </div>

            @if (Route::has('login'))
                <nav class="flex items-center gap-3">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" size="sm">
                            Dashboard
                        </flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">
                            Log in
                        </flux:button>

                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" size="sm">
                                Register
                            </flux:button>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>

        {{-- Hero --}}
        <section class="mx-auto max-w-4xl px-6 py-16 text-center lg:py-24">
            <h1 class="text-4xl font-semibold tracking-tight lg:text-5xl">
                Your household, organized
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
                Sunny helps your family collaborate on recipes<br class="hidden md:inline"> and keep track of what's in storage, all in one place.
            </p>

            @guest
                <div class="mt-8 flex items-center justify-center gap-3">
                    <flux:button :href="route('register')" variant="primary">
                        Get started
                    </flux:button>
                    <flux:button :href="route('login')" variant="ghost">
                        Log in
                    </flux:button>
                </div>
            @endguest
        </section>

        {{-- Features --}}
        <section class="mx-auto max-w-5xl px-6 pb-24">
            <div class="grid gap-8 md:grid-cols-3">
                {{-- Recipes --}}
                <flux:card>
                    <flux:avatar icon="book-open" icon-variant="outline" color="amber" class="mb-3" />
                    <flux:heading size="lg">{{ __('Recipes') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Save your favorite recipes, track ingredients, prep times, and nutrition info. Remix recipes to create your own variations.') }}
                    </flux:text>
                </flux:card>

                {{-- Inventory --}}
                <flux:card>
                    <flux:avatar icon="archive-box" icon-variant="outline" color="sky" class="mb-3" />
                    <flux:heading size="lg">{{ __('Inventory') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Organize your garage, basement, and pantry. Always know what you have and where it lives.') }}
                    </flux:text>
                </flux:card>

                {{-- Teams --}}
                <flux:card>
                    <flux:avatar icon="user-group" icon-variant="outline" color="violet" class="mb-3" />
                    <flux:heading size="lg">{{ __('Teams') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Invite family members to collaborate. Share recipes and inventory across your household with ease.') }}
                    </flux:text>
                </flux:card>

                {{-- Dashboard --}}
                <flux:card>
                    <flux:avatar icon="squares-2x2" icon-variant="outline" color="emerald" class="mb-3" />
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('Dashboard') }}</flux:heading>
                        <flux:badge size="sm" color="lime">{{ __('Soon') }}</flux:badge>
                    </div>
                    <flux:text class="mt-1">
                        {{ __('A family homepage with shared calendars, weather updates, and more, all at a glance.') }}
                    </flux:text>
                </flux:card>

                {{-- Collections --}}
                <flux:card>
                    <flux:avatar icon="rectangle-stack" icon-variant="outline" color="pink" class="mb-3" />
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('Collections') }}</flux:heading>
                        <flux:badge size="sm" color="lime">{{ __('Soon') }}</flux:badge>
                    </div>
                    <flux:text class="mt-1">
                        {{ __('Track collections outside of inventory like TCG cards, LEGO sets, and anything else you collect.') }}
                    </flux:text>
                </flux:card>

                {{-- Budgeting --}}
                <flux:card>
                    <flux:avatar icon="currency-dollar" icon-variant="outline" color="teal" class="mb-3" />
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ __('Budgeting') }}</flux:heading>
                        <flux:badge size="sm" color="lime">{{ __('Soon') }}</flux:badge>
                    </div>
                    <flux:text class="mt-1">
                        {{ __('Connect your YNAB account to view budgets and track spending right from Sunny.') }}
                    </flux:text>
                </flux:card>
            </div>
        </section>

        <flux:separator />

        {{-- Footer --}}
        <div class="text-center mt-4">
            <flux:text size="sm">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved') }}.</flux:text>
        </div>

        @fluxScripts
    </body>
</html>
