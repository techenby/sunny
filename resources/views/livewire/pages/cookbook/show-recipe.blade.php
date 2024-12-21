<flux:main>
    <div class="flex h-full">
        <div class="w-2/3 space-y-6 pr-4">
            <header class="flex">
                <flux:heading size="xl" level="1">{{ $recipe->name }}</flux:heading>
                <flux:spacer />
                <flux:button :href="route('cookbook.recipes.edit', $recipe)" size="sm" icon="pencil"
                    icon-variant="outline" square></flux:button>
            </header>

            <dl class="text-sm flex divide-x text-zinc-900 dark:text-zinc-200 divide-zinc-800/15 dark:divide-white/20">
                @if ($recipe->source)
                <div class="flex space-x-1 items-center pr-2">
                    <dt><flux:icon.bookmark-square class="size-5" /></dt>
                    <dd>{{ $recipe->shortened_source }}</dd>
                </div>
                @endif
                @if ($recipe->servings)
                <div class="flex space-x-1 items-center px-2">
                    <dt><flux:icon.users class="size-5" /></dt>
                    <dd>{{ $recipe->servings }}</dd>
                </div>
                @endif
                @if ($recipe->prep_time)
                <div class="flex flex-col space-y-1 items-center px-2">
                    <dt class="text-[10px] text-zinc-700 dark:text-zinc-400 uppercase font-light">Prep</dt>
                    <dd class="order-first">{{ $recipe->prep_time }}</dd>
                </div>
                @endif
                @if ($recipe->cook_time)
                <div class="flex flex-col space-y-1 items-center px-2">
                    <dt class="text-[10px] text-zinc-700 dark:text-zinc-400 uppercase font-light">Cook</dt>
                    <dd class="order-first">{{ $recipe->cook_time }}</dd>
                </div>
                @endif
                @if ($recipe->total_time)
                <div class="flex flex-col space-y-1 items-center pl-2">
                    <dt class="text-[10px] text-zinc-700 dark:text-zinc-400 uppercase font-light">Total</dt>
                    <dd class="order-first">{{ $recipe->total_time }}</dd>
                </div>
                @endif
            </dl>

            @if ($recipe->description)
                <p class="text-sm text-zinc-900 dark:text-zinc-400">
                    {{ $recipe->description }}
                </p>
                <flux:separator />
            @endif

            <div class="prose prose-zinc dark:prose-invert">
                {!! $recipe->instructions !!}
            </div>

            @if ($recipe->notes)
                <flux:separator />
                <div class="text-sm">
                    {{ $recipe->notes }}
                </div>
            @endif
        </div>
        <div class="w-1/3 space-y-6">
            @if ($url = $recipe->getFirstMediaUrl('thumb'))
            <div class="relative bg-blue-100 dark:bg-blue-800">
                <img src="{{ $url }}" alt="" class="object-cover h-48 w-96 rounded-md">
            </div>
            @endif
            <flux:card>
                <div class="prose prose-zinc dark:prose-invert prose-li:my-0 prose-ul:pl-2 prose-li:pl-0">
                    {!! $recipe->ingredients !!}
                </div>
            </flux:card>
        </div>
    </div>
</flux:main>
