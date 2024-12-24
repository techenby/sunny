<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.favicons')

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @fluxStyles
    @stack('head')

    @vite('resources/css/app.css')
</head>

<body class="font-sans antialiased min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky stashable class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="{{ route('dashboard') }}" wire:navigate class="max-lg:hidden">
            <x-application-logo class="max-lg:hidden block h-24 mx-auto w-auto fill-current text-gray-800 dark:text-gray-200" />
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" :href="route('dashboard')">{{ __('Home') }}</flux:navlist.item>
            <flux:navlist.item icon="users" :href="route('users')">{{ __('Users') }}</flux:navlist.item>

            <flux:navlist.group heading="Inventory" expandable>
                <flux:navlist.item :href="route('inventory.locations')">{{ __('Locations') }}</flux:navlist.item>
                <flux:navlist.item :href="route('inventory.bins')">{{ __('Bins') }}</flux:navlist.item>
                <flux:navlist.item :href="route('inventory.things')">{{ __('Things') }}</flux:navlist.item>
            </flux:navlist.group>
            <flux:navlist.group heading="Log Pose" expandable>
                <flux:navlist.item :href="route('log-pose.tiles')">{{ __('Tiles') }}</flux:navlist.item>
            </flux:navlist.group>
            <flux:navlist.group heading="Cookbook" expandable>
                <flux:navlist.item :href="route('cookbook.recipes')">{{ __('Recipes') }}</flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="cog-6-tooth" :href="route('profile')">{{ __('Profile') }}</flux:navlist.item>
        </flux:navlist>

        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:profile :avatar="'https://unavatar.io/' . auth()->user()->email" :name="auth()->user()->name" />

            <flux:menu>
                <livewire:layout.logout />
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" alignt="start">
            <flux:profile :avatar="'https://unavatar.io/' . auth()->user()->email" />

            <flux:menu>
                <livewire:layout.logout />
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    <flux:toast />

    @fluxScripts
    @vite('resources/js/app.js')
</body>

</html>
