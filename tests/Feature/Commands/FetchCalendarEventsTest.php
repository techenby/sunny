<?php

test('can fetch calendar events', function () {
    $this->artisan('app:fetch-calendar-events')->assertSuccessful();

    $this->assertDatabaseHas('dashboard_tiles', [
        'name' => 'calendar-andy',
    ]);
});
