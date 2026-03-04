<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
        <div class="mx-auto max-w-4xl px-6 py-10">
            <div class="mb-8 flex items-center gap-3">
                <a href="{{ route('home') }}">
                    <flux:avatar :src="asset('icon.png')" />
                </a>
                <flux:heading size="lg">{{ config('app.name') }}</flux:heading>
            </div>

            {{ $slot }}
        </div>

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
