<footer class="border-t border-zinc-950/5 py-8 dark:border-white/10">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 max-md:flex-col lg:px-8">
        <div class="flex items-center gap-2">
            <img src="{{ asset('icon.svg') }}" alt="" class="size-6 rounded-md" />
            <span class="font-semibold">Sunny Home</span>
        </div>

        <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-zinc-600 dark:text-zinc-400">
            <a href="{{ route('privacy') }}" class="hover:text-zinc-950 dark:hover:text-white">{{ __('Privacy') }}</a>
            <a href="{{ route('terms') }}" class="hover:text-zinc-950 dark:hover:text-white">{{ __('Terms') }}</a>
            <a href="{{ route('costs') }}" class="hover:text-zinc-950 dark:hover:text-white">{{ __('Costs') }}</a>
            <a href="{{ config('costs.sponsor_url') }}" class="hover:text-zinc-950 dark:hover:text-white">{{ __('Sponsor') }}</a>
            <a href="https://github.com/techenby/sunny" class="hover:text-zinc-950 dark:hover:text-white">{{ __('GitHub') }}</a>
            <a href="https://github.com/techenby/sunny/blob/main/LICENSE.md" class="hover:text-zinc-950 dark:hover:text-white">{{ __('MIT License') }}</a>
        </nav>

        <p class="text-sm text-zinc-600 dark:text-zinc-400">&copy; {{ date('Y') }} TechEnby</p>
    </div>
</footer>
