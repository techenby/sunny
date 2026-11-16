<?php

use App\Models\Recipe;
use App\Support\Recipes\Amount;
use App\Support\Recipes\RecipeLines;
use Laravel\Head\Facades\Head;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Component;

new class extends Component {
    private const int LONGEST_TIMER = 4 * 60 * 60;

    public Recipe $recipe;

    #[Session(key: 'cook-servings-{recipe.id}')]
    public ?int $servings = null;

    /** @var array<int, string> */
    #[Session(key: 'cook-checked-{recipe.id}')]
    public array $checked = [];

    #[Session(key: 'cook-step-{recipe.id}')]
    public int $step = 1;

    public function mount(): void
    {
        $this->servings ??= $this->recipe->servingCount();
    }

    public function changeServings(int $by): void
    {
        if ($this->servings !== null) {
            $this->servings = max(1, min(999, $this->servings + $by));
        }
    }

    public function toggleIngredient(int $index): void
    {
        $row = $this->recipe->ingredients[$index] ?? null;

        if ($row === null || $row['type'] === 'heading') {
            return;
        }

        if ($this->isChecked($index)) {
            unset($this->checked[$index]);
        } else {
            $this->checked[$index] = $row['text'];
        }
    }

    public function goToStep(int $step): void
    {
        $this->step = max(1, min($this->stepCount, $step));
    }

    public function isChecked(int $index): bool
    {
        return ($this->checked[$index] ?? null) === ($this->recipe->ingredients[$index]['text'] ?? false);
    }

    /** @return list<array{heading: string|null, start: int, rows: list<array<string, mixed>>}> */
    #[Computed]
    public function ingredientGroups(): array
    {
        $base = $this->recipe->servingCount();
        $factor = $base !== null && $this->servings !== null ? $this->servings / $base : 1.0;

        $rows = [];

        foreach ($this->recipe->ingredients ?? [] as $index => $row) {
            $rows[] = $row['type'] === 'heading'
                ? $row
                : [...$row, 'index' => $index, 'checked' => $this->isChecked($index), 'display' => Amount::scale($row, $factor)];
        }

        return RecipeLines::groups($rows);
    }

    /** @return list<array{heading: string|null, start: int, rows: list<array<string, mixed>>}> */
    #[Computed]
    public function stepGroups(): array
    {
        return RecipeLines::groups($this->recipe->instructions);
    }

    #[Computed]
    public function ingredientCount(): int
    {
        return collect($this->recipe->ingredients)->where('type', '!=', 'heading')->count();
    }

    #[Computed]
    public function checkedCount(): int
    {
        return collect($this->recipe->ingredients)->keys()->filter(fn (int $index): bool => $this->isChecked($index))->count();
    }

    #[Computed]
    public function stepCount(): int
    {
        return collect($this->recipe->instructions)->where('type', '!=', 'heading')->count();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<int>
     */
    public function timers(array $row): array
    {
        return collect($row['timers'] ?? [])
            ->pluck('seconds')
            ->filter(fn (int $seconds): bool => $seconds <= self::LONGEST_TIMER)
            ->values()
            ->all();
    }

    public function render()
    {
        Head::title(__('Cook :name', ['name' => $this->recipe->name]));

        return $this->view();
    }
}; ?>

<div
    class="space-y-6"
    x-data="{
        lock: null,
        async keepAwake() {
            try { this.lock = await navigator.wakeLock?.request('screen') } catch (error) {}
        },
    }"
    x-init="keepAwake()"
    x-on:visibilitychange.document="document.visibilityState === 'visible' && keepAwake()"
>
    <div class="space-y-3">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('recipes.index')" wire:navigate>{{ __('Recipes') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $recipe->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-5">
                @if ($recipe->thumb_url)
                    <img src="{{ $recipe->thumb_url }}" alt="" class="size-24 shrink-0 rounded-2xl object-cover" />
                @endif

                <div class="min-w-0 space-y-3">
                    <flux:heading size="xl" level="1">{{ $recipe->name }}</flux:heading>

                    @if ($servings !== null)
                        <div class="inline-flex items-center gap-3 rounded-full border border-zinc-200 py-1 pr-1 pl-4 dark:border-zinc-700">
                            <span class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                <flux:icon name="users" variant="micro" class="text-(--color-accent)" />
                                {{ __('Servings') }}
                            </span>
                            <span class="flex items-center gap-1 rounded-full border border-zinc-200 px-1 dark:border-zinc-700">
                                <flux:button size="xs" variant="ghost" icon="minus" wire:click="changeServings(-1)" :disabled="$servings <= 1" :aria-label="__('Fewer servings')" />
                                <span class="min-w-6 text-center text-sm font-medium tabular-nums" data-test="servings">{{ $servings }}</span>
                                <flux:button size="xs" variant="ghost" icon="plus" wire:click="changeServings(1)" :aria-label="__('More servings')" />
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <flux:button :href="route('recipes.show', $recipe)" icon="x-mark" wire:navigate>{{ __('Exit cook mode') }}</flux:button>
        </div>
    </div>

    <div class="grid items-start gap-6 lg:grid-cols-2">
        @if ($this->ingredientGroups)
            <flux:card>
                <div class="mb-3 flex items-baseline justify-between gap-4">
                    <flux:heading size="lg" level="2">{{ __('Ingredients') }}</flux:heading>
                    <flux:text size="sm" variant="subtle">
                        <span class="font-semibold text-(--color-accent)">{{ $this->checkedCount }}</span>
                        {{ __('of :count ready', ['count' => $this->ingredientCount]) }}
                    </flux:text>
                </div>

                <div class="space-y-6">
                    @foreach ($this->ingredientGroups as $group)
                        <div>
                            @if ($group['heading'])
                                <flux:heading level="3" class="mb-1">{{ $group['heading'] }}</flux:heading>
                            @endif

                            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($group['rows'] as $row)
                                    <li wire:key="ingredient-{{ $row['index'] }}">
                                        <button
                                            type="button"
                                            wire:click="toggleIngredient({{ $row['index'] }})"
                                            aria-pressed="{{ $row['checked'] ? 'true' : 'false' }}"
                                            class="flex w-full items-start gap-3 py-2 text-left"
                                        >
                                            @if ($row['checked'])
                                                <flux:icon name="check-circle" variant="solid" class="mt-0.5 size-5 shrink-0 text-(--color-accent)" />
                                            @else
                                                <span class="mt-0.5 size-5 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                                            @endif

                                            <span @class([
                                                'flex-1',
                                                'text-zinc-400 line-through dark:text-zinc-500' => $row['checked'],
                                                'text-zinc-600 dark:text-zinc-300' => ! $row['checked'],
                                            ])>
                                                @if ($row['display']['amount'])
                                                    <span @class(['font-semibold', 'text-zinc-800 dark:text-white' => ! $row['checked']])>{{ $row['display']['amount'] }}</span>
                                                @endif
                                                {{ $row['display']['text'] }}
                                            </span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        @if ($this->stepGroups)
            <flux:card>
                <div class="mb-3 flex items-baseline justify-between gap-4">
                    <flux:heading size="lg" level="2">{{ __('Instructions') }}</flux:heading>
                    <flux:text size="sm" variant="subtle">
                        {{ __('Step') }}
                        <span class="font-semibold text-(--color-accent)">{{ $step }}</span>
                        {{ __('of :count', ['count' => $this->stepCount]) }}
                    </flux:text>
                </div>

                <div class="space-y-4">
                    @foreach ($this->stepGroups as $group)
                        <div>
                            @if ($group['heading'])
                                <flux:heading level="3" class="mb-1">{{ $group['heading'] }}</flux:heading>
                            @endif

                            <ol class="space-y-1">
                                @foreach ($group['rows'] as $row)
                                    @php
                                        $number = $group['start'] + $loop->index;
                                        $timers = $this->timers($row);
                                    @endphp
                                    <li
                                        wire:key="step-{{ $number }}"
                                        @if ($number === $step) aria-current="step" @endif
                                        @class([
                                            '-mx-3 grid grid-cols-[2.5rem_1fr] gap-x-3 gap-y-2 rounded-xl border p-3',
                                            'border-(--color-accent)/30 bg-(--color-accent)/5 text-zinc-800 dark:text-white' => $number === $step,
                                            'border-transparent text-zinc-400 dark:text-zinc-500' => $number < $step,
                                            'border-transparent text-zinc-600 dark:text-zinc-300' => $number > $step,
                                        ])
                                    >
                                        <button
                                            type="button"
                                            wire:click="goToStep({{ $number }})"
                                            class="col-span-2 grid cursor-pointer grid-cols-subgrid rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-(--color-accent)"
                                        >
                                            <span @class([
                                                'text-3xl leading-7 font-medium tracking-tight',
                                                'text-(--color-accent)' => $number === $step,
                                                'text-(--color-accent)/55' => $number > $step,
                                                'text-zinc-400/60' => $number < $step,
                                            ])>{{ $number }}</span>

                                            <span class="space-y-2">
                                                @if ($number === $step)
                                                    <span class="inline-flex h-5 items-center rounded-md bg-(--color-accent) px-2 text-xs font-medium text-(--color-accent-foreground)">{{ __('Current step') }}</span>
                                                @endif

                                                <span class="block">{{ $row['text'] }}</span>
                                            </span>
                                        </button>

                                        @if ($timers)
                                            <div
                                                wire:ignore
                                                x-data="cookTimer(@js("cook-timer-{$recipe->id}-{$number}"), @js($timers), @js(__('Step :number timer is done', ['number' => $number])))"
                                                class="col-start-2 flex flex-wrap items-center gap-3"
                                            >
                                                <flux:button size="sm" x-on:click="toggle()">
                                                    <flux:icon name="play" variant="micro" class="text-(--color-accent)" x-show="! running" />
                                                    <flux:icon name="stop" variant="micro" class="text-(--color-accent)" x-show="running" x-cloak />
                                                    <span x-text="running ? @js(__('Stop timer')) : @js(__('Start timer'))">{{ __('Start timer') }}</span>
                                                    <span class="font-mono font-bold text-(--color-accent) tabular-nums" x-text="clock"></span>
                                                </flux:button>
                                                <flux:text size="sm" variant="subtle" x-show="upcoming" x-text="upcoming"></flux:text>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif
    </div>
</div>

@script
<script>
    Alpine.data('cookTimer', (key, durations, doneMessage) => ({
        index: 0,
        endsAt: null,
        remaining: durations[0],
        interval: null,

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(key) ?? 'null')

                if (saved && saved.index < durations.length) {
                    this.index = saved.index
                    this.endsAt = saved.endsAt
                }
            } catch (error) {}

            this.tick()

            if (this.endsAt) {
                this.interval = setInterval(() => this.tick(), 250)
            }
        },

        destroy() {
            clearInterval(this.interval)
        },

        get running() {
            return this.endsAt !== null
        },

        get clock() {
            const seconds = this.remaining % 60
            const minutes = Math.floor(this.remaining / 60) % 60
            const hours = Math.floor(this.remaining / 3600)
            const pad = (value) => String(value).padStart(2, '0')

            return hours > 0 ? `${hours}:${pad(minutes)}:${pad(seconds)}` : `${minutes}:${pad(seconds)}`
        },

        get upcoming() {
            return durations
                .slice(this.index + 1)
                .map((seconds) => `then ${seconds >= 3600 ? `${Math.round(seconds / 360) / 10} hr` : `${Math.round(seconds / 60)} min`}`)
                .join(', ')
        },

        toggle() {
            this.running ? this.stop() : this.start()
        },

        start() {
            this.endsAt = Date.now() + durations[this.index] * 1000
            this.save()
            this.tick()
            this.interval = setInterval(() => this.tick(), 250)
        },

        stop() {
            clearInterval(this.interval)
            this.endsAt = null
            this.save()
            this.tick()
        },

        tick() {
            if (this.endsAt === null) {
                this.remaining = durations[this.index]

                return
            }

            this.remaining = Math.max(0, Math.ceil((this.endsAt - Date.now()) / 1000))

            if (this.remaining === 0) {
                this.finish()
            }
        },

        finish() {
            this.index = (this.index + 1) % durations.length
            this.stop()
            this.chime()
            window.Flux?.toast({ heading: doneMessage, variant: 'success', duration: 0 })
        },

        chime() {
            try {
                const audio = new (window.AudioContext ?? window.webkitAudioContext)()

                ;[0, 0.35, 0.7].forEach((offset) => {
                    const tone = audio.createOscillator()
                    const volume = audio.createGain()

                    tone.frequency.value = 880
                    volume.gain.setValueAtTime(0.2, audio.currentTime + offset)
                    volume.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + offset + 0.25)
                    tone.connect(volume).connect(audio.destination)
                    tone.start(audio.currentTime + offset)
                    tone.stop(audio.currentTime + offset + 0.25)
                })
            } catch (error) {}
        },

        save() {
            try {
                localStorage.setItem(key, JSON.stringify({ index: this.index, endsAt: this.endsAt }))
            } catch (error) {}
        },
    }))
</script>
@endscript
