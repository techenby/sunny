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
                <flux:sidebar.item icon="calendar" href="#">Calendar</flux:sidebar.item>
                <flux:sidebar.item icon="arrow-path-rounded-square" href="#">Routines</flux:sidebar.item>
                <flux:sidebar.item icon="clipboard-document-list" href="#">Chore Chart</flux:sidebar.item>
                <flux:sidebar.item icon="queue-list" href="#">Lists</flux:sidebar.item>
                <flux:sidebar.item icon="cooking-pot" href="#">Meal Planning</flux:sidebar.item>
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
