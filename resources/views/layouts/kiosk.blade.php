<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => auth()->user()->currentTeam->appearance === \App\Enums\Appearance::Dark])>
    <head>
        {{-- Must run before @fluxAppearance so Flux applies the team's appearance instead of the device preference --}}
        <script>
            window.localStorage.setItem('flux.appearance', @js(auth()->user()->currentTeam->appearance->value));
        </script>

        @if (auth()->user()->currentTeam->rotation !== 0)
            {{-- Native popovers render in the browser's top layer, which ignores the body rotation
                 below. Removing the native API before Flux loads forces its popover polyfill, which
                 keeps dropdown panels inside the page so they rotate with it. --}}
            <script>
                delete HTMLElement.prototype.popover;
                delete HTMLElement.prototype.showPopover;
                delete HTMLElement.prototype.hidePopover;
                delete HTMLElement.prototype.togglePopover;
            </script>

            {{-- The polyfill injects its stylesheet in an @layer at runtime; declaring the layer
                 before app.css loads ranks it below Tailwind's layers so Flux's panel styling
                 (border, background, padding) wins over the polyfill's UA-like fallback styles. --}}
            <style>
                @layer popover-polyfill;
            </style>
        @endif

        @include('layouts.partials.head')

        {{-- Rotates the display for devices whose browser ignores the OS orientation setting --}}
        <style>
            html:has(> body[data-rotation]) {
                overflow: hidden;
            }

            body[data-rotation] {
                overflow: hidden;
                min-height: 0;
            }

            body[data-rotation="90"],
            body[data-rotation="270"] {
                width: 100vh;
                height: 100vw;
                transform-origin: top left;
            }

            body[data-rotation="90"] {
                transform: rotate(90deg) translateY(-100%);
            }

            body[data-rotation="180"] {
                width: 100vw;
                height: 100vh;
                transform: rotate(180deg);
            }

            body[data-rotation="270"] {
                transform: rotate(-90deg) translateX(-100%);
            }

            /* The polyfilled panels stay in the page, but Flux still positions them with
               unrotated viewport coordinates — anchor them to their trigger in page space
               instead. Right-aligned because the kiosk's dropdowns sit at the screen edge. */
            body[data-rotation] ui-dropdown {
                position: relative;
                display: inline-block;
            }

            body[data-rotation] ui-dropdown > [popover] {
                position: absolute !important;
                inset: auto !important;
                top: calc(100% + 4px) !important;
                right: 0 !important;
            }

            /* Flux only ships a polyfill-open display rule for ui-menu; plain flux:popover
               panels rely on the native :popover-open UA styles, which never fire with the
               API removed, so show any polyfill-opened panel explicitly. */
            body[data-rotation] ui-dropdown > [popover].\:popover-open {
                display: block;
            }
        </style>
    </head>
    <body
        class="min-h-screen bg-white dark:bg-zinc-800"
        @if (auth()->user()->currentTeam->rotation !== 0)
            data-rotation="{{ auth()->user()->currentTeam->rotation }}"
        @endif
    >
        <x-kiosk.sidebar>
            <livewire:kiosk.weather-tile />
            <x-kiosk.sidebar.item icon="calendar" :href="route('kiosk.calendar')" :current="request()->routeIs('kiosk.calendar')" wire:navigate>{{ __('Calendar') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="arrow-path-rounded-square" :href="route('kiosk.routines')" :current="request()->routeIs('kiosk.routines')" wire:navigate>{{ __('Routines') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="clipboard-document-list" :href="route('kiosk.chore-chart')" :current="request()->routeIs('kiosk.chore-chart')" wire:navigate>{{ __('Chores') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="queue-list" :href="route('kiosk.lists')" :current="request()->routeIs('kiosk.lists')" wire:navigate>{{ __('Lists') }}</x-kiosk.sidebar.item>
            <x-kiosk.sidebar.item icon="cooking-pot" :href="route('kiosk.meal-planning')" :current="request()->routeIs('kiosk.meal-planning')" wire:navigate>{{ __('Meals') }}</x-kiosk.sidebar.item>

            <button
                type="button"
                class="mt-auto flex h-20 flex-col items-center justify-center p-2 text-zinc-700 hover:text-(--color-accent-content) dark:text-zinc-300"
                data-kiosk-refresh
                x-data
                x-on:click="window.location.reload()"
            >
                <flux:icon icon="arrow-path" />
                <flux:text>{{ __('Refresh') }}</flux:text>
            </button>
        </x-kiosk.sidebar>

        <flux:main class="!p-0">
            {{ $slot }}
        </flux:main>

        <x-screensize />

        @persist('toast')
            <flux:toast />
        @endpersist

        @fluxScripts
    </body>
</html>
