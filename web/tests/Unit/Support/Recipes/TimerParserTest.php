<?php

use App\Support\Recipes\TimerParser;

test('it finds the durations in a step', function (string $text, array $seconds) {
    expect(array_column(TimerParser::parse($text), 'seconds'))->toBe($seconds);
})->with([
    'minutes' => ['Simmer for 20 minutes.', [1200]],
    'a range uses the shorter time' => ['Bake for 8–9 minutes.', [480]],
    'worded range' => ['Roast for 20 to 25 minutes, stirring halfway.', [1200]],
    'two timers in one step' => ['Bake for 8-9 minutes. Cool in the pan for 3-5 minutes before inverting.', [480, 180]],
    'hours and minutes combine' => ['Braise for 1 hour 30 minutes.', [5400]],
    'hours and minutes joined by and' => ['Cook for 1 hour and 15 minutes.', [4500]],
    'fractional hours' => ['Let it rise for 1 1/2 hours.', [5400]],
    'abbreviations' => ['Rest 5 mins, then sear 30 secs per side.', [300, 30]],
    'hyphenated' => ['Give the dough a 10-minute rest.', [600]],
    'temperatures are ignored' => ['Heat the oven to 425°F and season the chicken well.', []],
    'no duration' => ['Whisk the dry ingredients together.', []],
]);
