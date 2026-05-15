<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <x-kiosk.sidebar>
            <livewire:kiosk.weather-tile />
            <x-kiosk.sidebar.item icon="calendar" :href="route('kiosk.calendar')" :current="request()->routeIs('kiosk.calendar')" wire:navigate>{{ __('Calendar') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="arrow-path-rounded-square" :href="route('kiosk.routines')" :current="request()->routeIs('kiosk.routines')" wire:navigate>{{ __('Routines') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="clipboard-document-list" :href="route('kiosk.chore-chart')" :current="request()->routeIs('kiosk.chore-chart')" wire:navigate>{{ __('Chores') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="queue-list" :href="route('kiosk.lists')" :current="request()->routeIs('kiosk.lists')" wire:navigate>{{ __('Lists') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="cooking-pot" :href="route('kiosk.meal-planning')" :current="request()->routeIs('kiosk.meal-planning')" wire:navigate>{{ __('Meals') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="cog" :href="route('kiosk.settings')" :current="request()->routeIs('kiosk.settings')" wire:navigate>{{ __('Settings') }}</x-kiosk.sidebar.item>
        </x-kiosk.sidebar>

        <flux:main class="!p-0">
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
