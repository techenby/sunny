<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
            <flux:sidebar.header>
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.item icon="calendar" :href="route('kiosk.calendar')" :current="request()->routeIs('kiosk.calendar')" wire:navigate>Calendar</flux:sidebar.item>
                <flux:sidebar.item icon="arrow-path-rounded-square" :href="route('kiosk.routines')" :current="request()->routeIs('kiosk.routines')" wire:navigate>Routines</flux:sidebar.item>
                <flux:sidebar.item icon="clipboard-document-list" :href="route('kiosk.chore-chart')" :current="request()->routeIs('kiosk.chore-chart')" wire:navigate>Chore Chart</flux:sidebar.item>
                <flux:sidebar.item icon="queue-list" :href="route('kiosk.lists')" :current="request()->routeIs('kiosk.lists')" wire:navigate>Lists</flux:sidebar.item>
                <flux:sidebar.item icon="cooking-pot" :href="route('kiosk.meal-planning')" :current="request()->routeIs('kiosk.meal-planning')" wire:navigate>Meal Planning</flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
