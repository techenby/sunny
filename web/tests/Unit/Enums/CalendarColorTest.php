<?php

use App\Enums\CalendarColor;

test('every calendar colour has text that meets WCAG AA contrast on it', function (CalendarColor $color) {
    expect($color->contrastWith($color->textColor()))->toBeGreaterThanOrEqual(4.5);
})->with(CalendarColor::cases());

test('light colours get dark text and dark colours get white text', function () {
    expect(CalendarColor::Gold->textColor())->toBe(CalendarColor::DarkText)
        ->and(CalendarColor::Green->textColor())->toBe(CalendarColor::DarkText)
        ->and(CalendarColor::Indigo->textColor())->toBe(CalendarColor::LightText)
        ->and(CalendarColor::Red->textColor())->toBe(CalendarColor::LightText);
});
