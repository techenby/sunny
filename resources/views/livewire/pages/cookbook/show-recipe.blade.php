<flux:main class="space-y-6">
    <header class="lg:border-b lg:mb-8 lg:pb-4 space-y-6">
        <div class="flex">
            <flux:heading size="xl" level="1">{{ $recipe->name }}</flux:heading>
            <flux:spacer />
            @auth
            <flux:button :href="route('cookbook.recipes.edit', $recipe)" size="sm" icon="pencil"
                icon-variant="outline" square>
            <span class="sr-only">Edit</span></flux:button>
            @endauth
        </div>

        <dl class="text-sm flex flex-wrap divide-x text-zinc-900 dark:text-zinc-200 divide-zinc-800/15 dark:divide-white/20">
            @if ($recipe->source)
            <div class="flex space-x-1 items-center px-2 py-1">
                <dt><flux:icon.bookmark-square class="size-5" /></dt>
                <dd>
                    @if (str_contains($recipe->source, 'http'))
                    <flux:link variant="ghost" :href="$recipe->source">
                        {{ $recipe->shortened_source }}
                    </flux:link>
                    @else
                    {{ $recipe->shortened_source }}
                    @endif
                </dd>
            </div>
            @endif
            @if ($recipe->categories)
            <div class="flex space-x-1 items-center px-2 py-1">
                <dt><flux:icon.tag class="size-5" /></dt>
                <dd>{{ $recipe->categories }}</dd>
            </div>
            @endif
            @if ($recipe->servings)
            <div class="flex space-x-1 items-center px-2 py-1">
                <dt><flux:icon.users class="size-5" /></dt>
                <dd>{{ $recipe->servings }}</dd>
            </div>
            @endif
            @if ($recipe->prep_time)
            <div class="flex flex-col space-y-1 items-center px-2 py-1">
                <dt class="text-[10px] text-zinc-700 dark:text-zinc-400 uppercase font-light">Prep</dt>
                <dd class="order-first">{{ $recipe->prep_time }}</dd>
            </div>
            @endif
            @if ($recipe->cook_time)
            <div class="flex flex-col space-y-1 items-center px-2 py-1">
                <dt class="text-[10px] text-zinc-700 dark:text-zinc-400 uppercase font-light">Cook</dt>
                <dd class="order-first">{{ $recipe->cook_time }}</dd>
            </div>
            @endif
            @if ($recipe->total_time)
            <div class="flex flex-col space-y-1 items-center px-2 py-1">
                <dt class="text-[10px] text-zinc-700 dark:text-zinc-400 uppercase font-light">Total</dt>
                <dd class="order-first">{{ $recipe->total_time }}</dd>
            </div>
            @endif
        </dl>

        @if ($recipe->description)
            <p class="text-sm text-zinc-900 dark:text-zinc-400">
                {{ $recipe->description }}
            </p>
        @endif
    </header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:h-full">
        <div class="lg:col-span-2 space-y-6">
            <div class="prose prose-zinc dark:prose-invert">
                {!! $recipe->instructions !!}
            </div>

            @if ($recipe->notes)
                <flux:separator />
                <div class="prose prose-zinc dark:prose-invert prose-sm">
                    {{ $recipe->notes }}
                </div>
            @endif
        </div>
        <div class="order-first lg:order-last space-y-6">
            @if ($url = $recipe->getFirstMediaUrl('thumb'))
            <div class="relative bg-blue-100 dark:bg-blue-800 rounded-xl overflow-hidden">
                <img src="{{ $url }}" alt="" class="object-cover h-48 w-96">
            </div>
            @endif
            <flux:card>
                <div class="prose prose-zinc dark:prose-invert prose-li:my-0 prose-ul:pl-2 prose-li:pl-0">
                    {!! $recipe->ingredients !!}
                </div>
            </flux:card>
        </div>
    </div>

    @includeWhen(auth()->guest(), 'layouts.guest-footer')
</flux:main>
