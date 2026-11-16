<div>
    @if ($this->visible)
        <flux:card body="flush" data-test="getting-started">
            <flux:card.header>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:card.heading size="lg">{{ __('Get started with SunnyHome') }}</flux:card.heading>
                        <flux:text class="mt-0.5">{{ __('A few small things make it yours.') }}</flux:text>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <flux:text size="sm" class="whitespace-nowrap">{{ __(':done of :total done', ['done' => $this->doneCount, 'total' => count($this->jobs)]) }}</flux:text>
                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="{{ count($this->jobs) }}" aria-valuenow="{{ $this->doneCount }}">
                                <div class="h-full rounded-full bg-(--color-accent)" style="width: {{ round($this->doneCount / count($this->jobs) * 100) }}%"></div>
                            </div>
                        </div>

                        <flux:button size="sm" variant="ghost" icon:trailing="x-mark" wire:click="dismiss" data-test="dismiss-getting-started">{{ __('Dismiss') }}</flux:button>
                    </div>
                </div>
            </flux:card.header>

            <flux:card.body>
                <flux:card.bleed class="divide-y divide-zinc-900/5 dark:divide-white/10">
                    @foreach ($this->jobs as $job)
                        <div wire:key="getting-started-{{ $job['key'] }}" data-test="job-{{ $job['key'] }}" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-(--flux-bleed-x) py-4 sm:flex-nowrap">
                            <div class="flex min-w-0 flex-1 basis-full items-center gap-4 sm:basis-auto">
                                @if ($job['done'])
                                    <flux:icon.check-circle variant="solid" class="size-6 shrink-0 text-(--color-accent)" />
                                @else
                                    <span class="size-6 shrink-0 rounded-full border-2 border-zinc-300 dark:border-white/20"></span>
                                @endif

                                @unless ($job['done'])
                                    <flux:avatar size="sm" :icon="$job['icon']" :color="$job['color']" icon:variant="outline" class="hidden shrink-0 sm:flex" />
                                @endunless

                                <div class="min-w-0">
                                    <flux:heading @class(['line-through text-zinc-500! dark:text-zinc-400!' => $job['done']])>{{ $job['heading'] }}</flux:heading>
                                    <flux:text size="sm">{{ $job['text'] }}</flux:text>
                                </div>
                            </div>

                            @if (! $job['done'] && $job['action'])
                                <flux:button
                                    size="sm"
                                    :variant="$this->nextJob === $job['key'] ? 'primary' : 'outline'"
                                    :icon="$this->nextJob === $job['key'] ? 'plus' : null"
                                    :href="$job['href']"
                                    wire:navigate
                                    class="ms-10 sm:ms-0"
                                >
                                    {{ $job['action'] }}
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </flux:card.bleed>
            </flux:card.body>
        </flux:card>
    @endif
</div>
