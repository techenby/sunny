<?php

use App\Models\KioskDevice;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('one network can only open so many pairing screens a minute', function () {
    foreach (range(1, 20) as $attempt) {
        $this->get(route('kiosk.index'))->assertOk();
    }

    $this->get(route('kiosk.index'))->assertTooManyRequests();
});

test('screens that were never paired are cleared out a day after their code expires', function () {
    $stale = KioskDevice::factory()->pending()->create(['expires_at' => now()->subDays(2)]);
    $waiting = KioskDevice::factory()->pending()->create();
    $paired = KioskDevice::factory()->paired()->create(['expires_at' => now()->subDays(2)]);

    collect(resolve(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === 'Delete kiosk screens that were never paired')
        ->run(app());

    expect($stale->fresh())->toBeNull()
        ->and($waiting->fresh())->not->toBeNull()
        ->and($paired->fresh())->not->toBeNull();
});
