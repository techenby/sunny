@props(['timezone'])

<div
    x-data='{
        interval: null,
        now: new Date(),
        timezone: "{{ $timezone }}",

        init() {
            this.interval = setInterval(() => {
                this.now = new Date()
            }, 1000)
        },

        destroy() {
            clearInterval(this.interval)
        },

        formattedDateTime() {
            const parts = new Intl.DateTimeFormat("en-US", {
                day: "numeric",
                hour: "numeric",
                minute: "2-digit",
                month: "short",
                timeZone: this.timezone,
                weekday: "short",
            }).formatToParts(this.now).reduce((carry, part) => {
                carry[part.type] = part.value

                return carry
            }, {})

            return `${parts.weekday}, ${parts.month} ${parts.day} ${parts.hour}:${parts.minute} ${parts.dayPeriod}`
        },
    }'
>
    <flux:heading size="xl" x-text="formattedDateTime()">
        {{ Carbon\CarbonImmutable::now($this->timezoneName())->format('D, M j g:i A') }}
    </flux:heading>
</div>
