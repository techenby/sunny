<div
    class="mt-6 flex flex-col items-center gap-5"
    wire:poll.300s.visible
    x-on:kiosk-screensaver-shown.window="$wire.$refresh()"
>
    @if ($this->weather)
        <div class="flex items-center gap-3 text-3xl" data-screensaver-weather>
            @if ($this->weather['icon'])
                <img
                    src="https://openweathermap.org/img/wn/{{ $this->weather['icon'] }}@2x.png"
                    alt="{{ $this->weather['description'] }}"
                    class="-my-3 size-16"
                    x-show="! isNight()"
                />
            @endif

            <span class="font-semibold tabular-nums">{{ $this->weather['temp'] }}°</span>

            @if ($this->weather['description'])
                <span class="capitalize" x-bind:class="isNight() ? '' : 'text-zinc-300'">{{ $this->weather['description'] }}</span>
            @endif

            <span class="tabular-nums" x-bind:class="isNight() ? 'opacity-70' : 'text-zinc-400'">
                {{ __('H') }} {{ $this->weather['high'] }}° · {{ __('L') }} {{ $this->weather['low'] }}°
            </span>
        </div>
    @endif

    @if ($this->nextEvent)
        <div
            class="flex max-w-4xl items-center gap-3 text-2xl"
            data-screensaver-next-event
            x-data="{ startsAt: Date.parse(@js($this->nextEvent['startsAt'])) }"
        >
            <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $this->nextEvent['color'] }}" x-show="! isNight()"></span>
            <span class="shrink-0" x-bind:class="isNight() ? 'opacity-70' : 'text-zinc-400'">{{ $this->nextEvent['label'] }}</span>
            <span class="truncate font-semibold">{{ $this->nextEvent['title'] }}</span>
            <span
                class="shrink-0 tabular-nums"
                x-bind:class="isNight() ? 'opacity-70' : 'text-zinc-300'"
                x-text="(() => {
                    const minutes = Math.ceil((startsAt - now) / 60_000)

                    return minutes > 0 && minutes < 60 ? `in ${minutes} min` : @js($this->nextEvent['time'])
                })()"
            >{{ $this->nextEvent['time'] }}</span>
        </div>
    @endif
</div>
