@props(['team'])

@persist('kiosk-idle')
    <div
        data-kiosk-screensaver
        x-data="{
            screensaverAfter: @js($team->screensaver_after * 60_000),
            returnHomeAfter: @js($team->return_home_after * 60_000),
            routineHold: 30 * 60_000,
            homeUrl: @js(route('kiosk.calendar', absolute: false)),
            timezone: @js($team->timezone),
            nightStartsAt: @js($team->night_starts_at),
            nightEndsAt: @js($team->night_ends_at),
            lastActivityAt: Date.now(),
            returnedHomeFor: null,
            showing: false,
            now: new Date(),
            shift: '',

            init() {
                for (const type of ['pointerdown', 'keydown', 'wheel']) {
                    document.addEventListener(type, () => this.lastActivityAt = Date.now(), { capture: true, passive: true })
                }

                setInterval(() => this.tick(), 1000)
                setInterval(() => this.shiftPixels(), 60_000)
            },

            tick() {
                this.now = new Date()

                const idleFor = Date.now() - this.lastActivityAt

                if (document.querySelector('[data-routine-in-progress]') && idleFor < this.routineHold) {
                    return
                }

                if (this.returnHomeAfter && idleFor >= this.returnHomeAfter && this.returnedHomeFor !== this.lastActivityAt) {
                    this.returnedHomeFor = this.lastActivityAt

                    if (window.location.pathname + window.location.search !== this.homeUrl) {
                        Livewire.navigate(this.homeUrl)
                    }
                }

                if (this.screensaverAfter && idleFor >= this.screensaverAfter && ! this.showing) {
                    this.showing = true
                    this.$dispatch('kiosk-screensaver-shown')
                }
            },

            wake() {
                this.lastActivityAt = Date.now()
                this.showing = false
            },

            shiftPixels() {
                const offset = () => Math.round(Math.random() * 24) - 12

                this.shift = `transform: translate(${offset()}px, ${offset()}px)`
            },

            parts() {
                return new Intl.DateTimeFormat('en-US', {
                    weekday: 'long',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hourCycle: 'h12',
                    timeZone: this.timezone,
                }).formatToParts(this.now).reduce((carry, part) => ({ ...carry, [part.type]: part.value }), {})
            },

            minutesIntoDay() {
                const [hours, minutes] = new Intl.DateTimeFormat('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hourCycle: 'h23',
                    timeZone: this.timezone,
                }).format(this.now).split(':').map(Number)

                return hours * 60 + minutes
            },

            isNight() {
                if (! this.nightStartsAt || ! this.nightEndsAt) {
                    return false
                }

                const toMinutes = (time) => time.split(':').map(Number).reduce((hours, minutes) => hours * 60 + minutes)
                const startsAt = toMinutes(this.nightStartsAt)
                const endsAt = toMinutes(this.nightEndsAt)
                const now = this.minutesIntoDay()

                return startsAt < endsAt
                    ? now >= startsAt && now < endsAt
                    : now >= startsAt || now < endsAt
            },
        }"
        x-show="showing"
        x-on:click="wake()"
        x-bind:class="isNight() ? 'bg-black text-red-700/80' : 'bg-zinc-950 text-white'"
        x-bind:data-night="isNight()"
        role="button"
        aria-label="{{ __('Wake display') }}"
        class="fixed inset-0 z-50 flex cursor-pointer select-none flex-col items-center justify-center"
        style="display: none"
    >
        <div class="flex flex-col items-center gap-4 text-center" x-bind:style="shift">
            <div class="font-bold leading-none tracking-tight tabular-nums" x-bind:class="isNight() ? 'text-[12rem]' : 'text-[10rem]'">
                <span x-text="`${parts().hour}:${parts().minute}`"></span><span class="ml-3 text-5xl font-semibold" x-text="parts().dayPeriod"></span>
            </div>

            <div class="text-3xl font-medium" x-bind:class="isNight() ? 'opacity-70' : 'text-zinc-300'" x-text="`${parts().weekday}, ${parts().month} ${parts().day}`"></div>

            <livewire:kiosk.screensaver-details :team-id="$team->id" lazy />
        </div>
    </div>
@endpersist
