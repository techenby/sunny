<?php

use App\Actions\CurrentPayPeriod;
use Illuminate\Support\Carbon;

test('can get current pay period', function ($now, $start, $end) {
    Carbon::setTestNow($now);

    $result = (new CurrentPayPeriod)();

    expect($result['start']->format('Y-m-d'))->toBe($start);
    expect($result['end']->format('Y-m-d'))->toBe($end);
})->with([
    ['2024-11-22', '2024-11-22', '2024-12-06'],
    ['2024-11-23', '2024-11-22', '2024-12-06'],
    ['2024-11-24', '2024-11-22', '2024-12-06'],
    ['2024-11-25', '2024-11-22', '2024-12-06'],
    ['2024-11-26', '2024-11-22', '2024-12-06'],
    ['2024-11-27', '2024-11-22', '2024-12-06'],
    ['2024-11-28', '2024-11-22', '2024-12-06'],
    ['2024-11-29', '2024-11-22', '2024-12-06'],
    ['2024-11-30', '2024-11-22', '2024-12-06'],
    ['2024-12-01', '2024-11-22', '2024-12-06'],
    ['2024-12-02', '2024-11-22', '2024-12-06'],
    ['2024-12-03', '2024-11-22', '2024-12-06'],
    ['2024-12-04', '2024-11-22', '2024-12-06'],
    ['2024-12-05', '2024-11-22', '2024-12-06'],
    ['2024-12-06', '2024-12-06', '2024-12-20'],
    ['2024-12-07', '2024-12-06', '2024-12-20'],
    ['2024-12-08', '2024-12-06', '2024-12-20'],
    ['2024-12-09', '2024-12-06', '2024-12-20'],
    ['2024-12-10', '2024-12-06', '2024-12-20'],
    ['2024-12-11', '2024-12-06', '2024-12-20'],
    ['2024-12-12', '2024-12-06', '2024-12-20'],
    ['2024-12-13', '2024-12-06', '2024-12-20'],
    ['2024-12-14', '2024-12-06', '2024-12-20'],
    ['2024-12-15', '2024-12-06', '2024-12-20'],
    ['2024-12-16', '2024-12-06', '2024-12-20'],
    ['2024-12-17', '2024-12-06', '2024-12-20'],
    ['2024-12-18', '2024-12-06', '2024-12-20'],
    ['2024-12-19', '2024-12-06', '2024-12-20'],
    ['2024-12-20', '2024-12-20', '2025-01-03'],
    ['2024-12-21', '2024-12-20', '2025-01-03'],
]);
