<?php

use App\Enums\CalendarColor;
use App\Enums\CalendarEventStyle;

test('an unanswered invite is outlined instead of filled', function () {
    $this->blade('<x-kiosk.calendar.event-surface :event="$event" :event-style="$eventStyle">Party</x-kiosk.calendar.event-surface>', [
        'event' => ['feed_color' => CalendarColor::Red, 'response_status' => 'NEEDS-ACTION'],
        'eventStyle' => CalendarEventStyle::Filled,
    ])
        ->assertSee('data-event-style="outlined"', false)
        ->assertSee('border-dashed', false)
        ->assertDontSee('bg-(--event-color)', false);
});

test('an unanswered invite keeps a dashed stripe', function (CalendarEventStyle $eventStyle) {
    $this->blade('<x-kiosk.calendar.event-surface :event="$event" :event-style="$eventStyle">Party</x-kiosk.calendar.event-surface>', [
        'event' => ['feed_color' => CalendarColor::Red, 'response_status' => 'NEEDS-ACTION'],
        'eventStyle' => $eventStyle,
    ])
        ->assertSee('data-event-style="' . $eventStyle->value . '"', false)
        ->assertSee('[border-left-style:dashed]', false);
})->with([CalendarEventStyle::Stripe, CalendarEventStyle::Tinted]);

test('a declined event is struck through in every style', function (CalendarEventStyle $eventStyle) {
    $this->blade('<x-kiosk.calendar.event-surface :event="$event" :event-style="$eventStyle">Party</x-kiosk.calendar.event-surface>', [
        'event' => ['feed_color' => CalendarColor::Red, 'response_status' => 'DECLINED'],
        'eventStyle' => $eventStyle,
    ])
        ->assertSee('line-through', false)
        ->assertSee('--event-color: #dc2626', false);
})->with(CalendarEventStyle::cases());
