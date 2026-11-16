<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
        <header class="flex h-16 items-center justify-between border-b border-zinc-200 px-4 sm:px-8 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <flux:avatar size="xs" :src="asset('icon.svg')" />
                <flux:heading>SunnyHome</flux:heading>
            </div>

            <div class="flex items-center gap-2">
                <flux:text class="hidden sm:block">{{ auth()->user()->name }}</flux:text>
                <flux:avatar size="sm" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-16">
            {{ $slot }}
        </main>

        <x-screensize />

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
