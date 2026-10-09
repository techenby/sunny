@php
    $featureGroups = [
        __('In the kitchen') => [
            ['icon' => 'book-open', 'name' => __('Recipes'), 'description' => __('Import recipes from any website, remix family favorites into your own versions, and share a link with anyone.')],
            ['icon' => 'clipboard-document-list', 'name' => __('Lists'), 'description' => __('To-do, shopping, and wish lists that the whole household can check off, or keep one just for yourself.')],
        ],
        __('Around the house') => [
            ['icon' => 'archive-box', 'name' => __('Inventory'), 'description' => __('Map the garage, basement, and pantry into locations and bins. Print QR labels so you know what is in a box without opening it.')],
            ['icon' => 'arrow-path', 'name' => __('Routines'), 'description' => __('Morning routines and chore charts that reset themselves every day, on chosen weekdays, or once a month.')],
        ],
        __('Across the family') => [
            ['icon' => 'calendar-days', 'name' => __('Calendars'), 'description' => __('Subscribe to Google, Proton, or any iCal feed and see everyone\'s plans side by side.')],
            ['icon' => 'user-group', 'name' => __('Households'), 'description' => __('Invite family members to share everything, and switch between households when you help run more than one.')],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark antialiased">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-dvh bg-white text-zinc-950 dark:bg-zinc-900 dark:text-white">
        <div class="isolate">
            <header class="px-6 py-4 lg:px-8">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-6">
                    <flux:brand href="/" :logo="asset('icon.svg')" name="Sunny Home" />

                    <nav class="flex items-center gap-8 text-sm font-medium text-zinc-600 max-md:hidden dark:text-zinc-400">
                        <a href="#features" class="hover:text-zinc-950 dark:hover:text-white">{{ __('Features') }}</a>
                        <a href="#kiosk" class="hover:text-zinc-950 dark:hover:text-white">{{ __('Kiosk') }}</a>
                        <a href="#mobile" class="hover:text-zinc-950 dark:hover:text-white">{{ __('Mobile') }}</a>
                        <a href="#assistant" class="hover:text-zinc-950 dark:hover:text-white">{{ __('AI assistant') }}</a>
                    </nav>

                    <div class="flex items-center gap-2">
                        @auth
                            <flux:button :href="route('dashboard')" size="sm">{{ __('Dashboard') }}</flux:button>
                        @else
                            <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button>

                            @if (Route::has('register'))
                                <flux:button :href="route('register')" size="sm">{{ __('Register') }}</flux:button>
                            @endif
                        @endauth
                    </div>
                </div>
            </header>

            <main>
                <section class="py-16 sm:py-24">
                    <div class="mx-auto grid max-w-7xl items-center gap-x-16 gap-y-12 px-6 lg:grid-cols-2 lg:px-8">
                        <div>
                            <flux:heading level="1" size="2xl" class="max-w-[24ch] tracking-tight text-balance sm:text-6xl">
                                {{ __('Your household, organized') }}
                            </flux:heading>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                {{ __('Recipes, lists, routines, calendars, and everything in storage, shared with your whole family. On the web, on your phone, and on the wall.') }}
                            </flux:text>
                            <x-welcome.cta-buttons class="mt-10" />
                            <flux:text class="mt-6">
                                {{ __('Free and open source.') }}
                                <flux:link href="https://github.com/techenby/sunny" external>{{ __('View the code on GitHub') }}</flux:link>
                            </flux:text>
                        </div>

                        <div class="relative lg:pb-24">
                            <x-welcome.dashboard-preview class="lg:mr-16" />
                            <x-welcome.phone-preview size="sm" class="absolute right-0 bottom-0 w-44 max-lg:hidden" />
                        </div>
                    </div>
                </section>

                <section id="features" class="scroll-mt-8 py-16 sm:py-24">
                    <div class="mx-auto max-w-7xl px-6 lg:px-8">
                        <div>
                            <flux:text class="font-mono font-medium tracking-wide text-accent-content uppercase">{{ __('Everything in one place') }}</flux:text>
                            <flux:heading level="2" size="2xl" class="mt-3 max-w-[35ch] tracking-tight text-balance">
                                {{ __('One home base for the whole family') }}
                            </flux:heading>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                {{ __('Stop juggling a recipe app, a notes app, a shared calendar, and a spreadsheet of what is in the garage.') }}
                            </flux:text>
                        </div>

                        <div class="mt-16 grid gap-x-16 gap-y-12 lg:grid-cols-3">
                            @foreach ($featureGroups as $group => $groupFeatures)
                                <div class="flex flex-col gap-8 rounded-2xl bg-zinc-50 p-8 dark:bg-white/5 dark:inset-ring dark:inset-ring-white/5">
                                    <flux:heading level="3" class="text-zinc-500 dark:text-zinc-400">{{ $group }}</flux:heading>
                                    <dl class="flex flex-col gap-8">
                                        @foreach ($groupFeatures as $feature)
                                            <div>
                                                <dt class="flex items-center gap-3 text-base/7 font-semibold">
                                                    <flux:icon :icon="$feature['icon']" class="shrink-0 text-accent-content" />
                                                    {{ $feature['name'] }}
                                                </dt>
                                                <dd class="mt-2 text-base/7 text-pretty text-zinc-600 dark:text-zinc-400">{{ $feature['description'] }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section id="kiosk" class="scroll-mt-8 bg-zinc-50 py-16 sm:py-24 dark:border-y dark:border-white/10 dark:bg-zinc-900">
                    <div class="mx-auto max-w-7xl px-6 lg:px-8">
                        <div>
                            <flux:text class="font-mono font-medium tracking-wide text-accent-content uppercase">{{ __('Kiosk') }}</flux:text>
                            <flux:heading level="2" size="2xl" class="mt-3 max-w-[35ch] tracking-tight text-balance">
                                {{ __('Put the day on the wall') }}
                            </flux:heading>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                {{ __('Pair a spare tablet as a family kiosk. Everyone can see what is happening today and check things off without picking up a phone.') }}
                            </flux:text>
                        </div>

                        <x-welcome.kiosk-preview class="mt-16" />

                        <dl class="mt-16 grid gap-x-16 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="flex items-center gap-3 text-base/7 font-semibold">
                                    <flux:icon.squares-2x2 class="shrink-0 text-accent-content" />
                                    {{ __('Everything at a glance') }}
                                </dt>
                                <dd class="mt-2 text-base/7 text-pretty text-zinc-600 dark:text-zinc-400">
                                    {{ __('The calendar, routines, lists, and meal plan are one tap apart, with the local weather always in view.') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-3 text-base/7 font-semibold">
                                    <flux:icon.hand-raised class="shrink-0 text-accent-content" />
                                    {{ __('Made for tapping') }}
                                </dt>
                                <dd class="mt-2 text-base/7 text-pretty text-zinc-600 dark:text-zinc-400">
                                    {{ __('Big touch targets so kids can finish their routines and anyone can tick off the grocery list.') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="flex items-center gap-3 text-base/7 font-semibold">
                                    <flux:icon.moon class="shrink-0 text-accent-content" />
                                    {{ __('Quiet when idle') }}
                                </dt>
                                <dd class="mt-2 text-base/7 text-pretty text-zinc-600 dark:text-zinc-400">
                                    {{ __('When nobody is around the screen switches to a big clock, with a dim night mode for the hours you choose.') }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section id="mobile" class="scroll-mt-8 py-16 sm:py-24">
                    <div class="mx-auto grid max-w-7xl items-center gap-x-16 gap-y-12 px-6 lg:grid-cols-2 lg:px-8">
                        <div>
                            <div class="flex items-center gap-3">
                                <flux:text class="font-mono font-medium tracking-wide text-accent-content uppercase">{{ __('Mobile app') }}</flux:text>
                                <flux:badge size="sm" color="amber">{{ __('Beta') }}</flux:badge>
                            </div>
                            <flux:heading level="2" size="2xl" class="mt-3 max-w-[35ch] tracking-tight text-balance">
                                {{ __('Works in the basement, too') }}
                            </flux:heading>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                {{ __('The Sunny Home app, now in beta, saves every change on your phone first, so it keeps working where the Wi-Fi does not reach.') }}
                            </flux:text>

                            <dl class="mt-10 flex flex-col gap-8">
                                <div>
                                    <dt class="flex items-center gap-3 text-base/7 font-semibold">
                                        <flux:icon.qr-code class="shrink-0 text-accent-content" />
                                        {{ __('Scan a label, see the bin') }}
                                    </dt>
                                    <dd class="mt-2 text-base/7 text-pretty text-zinc-600 dark:text-zinc-400">
                                        {{ __('Point your camera at any Sunny QR label to open that location, bin, or item.') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="flex items-center gap-3 text-base/7 font-semibold">
                                        <flux:icon.camera class="shrink-0 text-accent-content" />
                                        {{ __('Add items with a photo') }}
                                    </dt>
                                    <dd class="mt-2 text-base/7 text-pretty text-zinc-600 dark:text-zinc-400">
                                        {{ __('Snap what is going into a box, one photo at a time, instead of typing it all out.') }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="flex items-center gap-3 text-base/7 font-semibold">
                                        <flux:icon.arrow-path-rounded-square class="shrink-0 text-accent-content" />
                                        {{ __('Syncs when you are back') }}
                                    </dt>
                                    <dd class="mt-2 text-base/7 text-pretty text-zinc-600 dark:text-zinc-400">
                                        {{ __('Lists, routines, and inventory changes upload automatically once you are online again.') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <x-welcome.phone-preview class="mx-auto w-full max-w-72" />
                    </div>
                </section>

                <section id="assistant" class="scroll-mt-8 py-16 sm:py-24 dark:border-t dark:border-white/10">
                    <div class="mx-auto grid max-w-7xl items-center gap-x-16 gap-y-12 px-6 lg:grid-cols-2 lg:px-8">
                        <x-welcome.assistant-preview class="max-lg:order-last" />

                        <div>
                            <flux:text class="font-mono font-medium tracking-wide text-accent-content uppercase">{{ __('AI assistant') }}</flux:text>
                            <flux:heading level="2" size="2xl" class="mt-3 max-w-[35ch] tracking-tight text-balance">
                                {{ __('Ask your assistant to handle it') }}
                            </flux:heading>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                {{ __('Sunny Home has a built-in MCP server. Connect Claude or any other assistant that supports MCP, sign in once, and it can work with your household for you.') }}
                            </flux:text>

                            <ul role="list" class="mt-10 flex flex-col gap-4 text-base/7 text-zinc-600 dark:text-zinc-400">
                                <li class="flex gap-3">
                                    <flux:icon.check variant="micro" class="h-lh shrink-0 text-accent-content" />
                                    {{ __('Plan meals and build the grocery list from your recipes') }}
                                </li>
                                <li class="flex gap-3">
                                    <flux:icon.check variant="micro" class="h-lh shrink-0 text-accent-content" />
                                    {{ __('Import a recipe by pasting a link into the chat') }}
                                </li>
                                <li class="flex gap-3">
                                    <flux:icon.check variant="micro" class="h-lh shrink-0 text-accent-content" />
                                    {{ __('Find out which bin the camping stove ended up in') }}
                                </li>
                                <li class="flex gap-3">
                                    <flux:icon.check variant="micro" class="h-lh shrink-0 text-accent-content" />
                                    {{ __('Check what is on the calendar and which chores are left') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section id="built-by" class="scroll-mt-8 border-t border-zinc-950/5 py-16 sm:py-24 dark:border-white/10">
                    <div class="mx-auto grid max-w-7xl items-center gap-x-16 gap-y-12 px-6 lg:grid-cols-2 lg:px-8">
                        <div>
                            <flux:text class="font-mono font-medium tracking-wide text-accent-content uppercase">{{ __('Meet the maker') }}</flux:text>
                            <flux:heading level="2" size="2xl" class="mt-3 max-w-[35ch] tracking-tight text-balance">
                                {{ __('Built by Andy Swick') }}
                            </flux:heading>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                I'm <a href="https://techenby.com" class="underline underline-offset-4 hover:text-zinc-950 dark:hover:text-white">Andy Swick</a>,
                                aka <a href="https://github.com/techenby" class="underline underline-offset-4 hover:text-zinc-950 dark:hover:text-white">TechEnby</a>, a Lead Programmer at <a href="https://tighten.com" class="underline underline-offset-4 hover:text-zinc-950 dark:hover:text-white">Tighten</a>.
                                Sunny Home is my personal project. It started as a way for me to experiment with new technologies and techniques,
                                and grew into one place for everything I used to spread across a bunch of different apps. Now I use almost every feature daily,
                                from finding where an extension cord ended up, to deciding what to make for dinner, to keeping track of what I need to do for work.
                            </flux:text>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                It's not just me, either. My dad uses it to organize his garage, so my mom hears "where's the drill?" a lot less often.
                                My partner checks it every morning to make sure they've finished their routine before work. We all depend on it, so it isn't going anywhere.
                            </flux:text>
                            <flux:text size="xl" class="mt-6 max-w-[48ch] text-pretty">
                                There are no ads, and I'll never sell your data. Because Sunny Home is open source, you don't have to take my word for it:
                                you can read exactly how your data is handled, open an issue when something breaks, or run your own copy.
                            </flux:text>

                            <div class="mt-10 flex flex-wrap gap-3">
                                @foreach (array_filter(['Report an issue' => 'https://github.com/techenby/sunny/issues', 'Read the docs' => Route::has('laradocs.index') ? route('laradocs.index') : null, 'Sunny Home source' => 'https://github.com/techenby/sunny']) as $label => $url)
                                    <a href="{{ $url }}" class="rounded-full border border-zinc-950/10 px-4 py-1.5 text-sm text-zinc-600 hover:border-zinc-950/20 hover:text-zinc-950 dark:border-white/10 dark:text-zinc-400 dark:hover:border-white/20 dark:hover:text-white">{{ $label }}</a>
                                @endforeach
                            </div>
                        </div>

                        <div class="mx-auto w-full max-w-80 [--padding:--spacing(2)] [--radius:var(--radius-2xl)] rounded-(--radius) bg-zinc-950/5 p-(--padding) ring-1 ring-zinc-950/5 ring-inset dark:bg-white/5 dark:ring-white/10">
                            <img src="https://github.com/techenby.png" alt="Andy Swick" class="aspect-square w-full rounded-[calc(var(--radius)-var(--padding))] object-cover shadow-lg ring-1 ring-zinc-950/10 dark:shadow-none dark:ring-white/10" />
                        </div>
                    </div>
                </section>

                <section class="border-t border-zinc-950/5 py-16 sm:py-24 dark:border-white/10">
                    <div class="mx-auto max-w-7xl px-6 lg:px-8">
                        <div class="text-center">
                            <flux:heading level="2" size="2xl" class="mx-auto max-w-[35ch] tracking-tight text-balance">
                                {{ __('Bring your household together') }}
                            </flux:heading>
                            <flux:text size="xl" class="mx-auto mt-6 max-w-[48ch] text-pretty">
                                {{ __('Set up your household in a minute, then invite everyone else.') }}
                            </flux:text>
                            <x-welcome.cta-buttons class="mt-10 justify-center" />
                        </div>
                    </div>
                </section>
            </main>

            @include('layouts.partials.footer')
        </div>

        @fluxScripts
    </body>
</html>
