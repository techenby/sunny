<?php

use function Livewire\Volt\state;

state(['position' => '']);
?>

<x-dashboard-tile :position="$position">
    <div x-data="{
        timezone: 'America/Chicago',
        now: null,
        time: null,
        date: null,
        init() {
            let now = new Date()
            this.time = now.toLocaleTimeString('en-US', { timeZone: this.timezone, timeStyle: 'short', })
            this.date = now.toLocaleDateString('en-US', { timeZone: this.timezone, dateStyle: 'full', })
            setInterval(() => {
                let now = new Date()
                this.time = now.toLocaleTimeString('en-US', { timeZone: this.timezone, timeStyle: 'short', })
                this.date = now.toLocaleDateString('en-US', { timeZone: this.timezone, dateStyle: 'full', })
            }, 1000);
        }
    }"
    class="h-full flex items-center justify-center flex-col"
    >
        <p x-text="time" class="text-3xl text-default font-bold"></p>
        <p x-text="date" class="text-dimmed"></p>
    </div>
</x-dashboard-tile>
