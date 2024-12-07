<?php

namespace App\Actions;

class CurrentPayPeriod
{
    public function __invoke()
    {
        $now = now();

        if ($now->isFriday() && $now->week % 2 === 1) {
            return [
                'start' => $now,
                'end' => $now->clone()->addDays(14),
            ];
        } elseif ($now->modify('previous friday')->week % 2 === 1) {
            return [
                'start' => $now,
                'end' => $now->clone()->addDays(14),
            ];
        } elseif ($now->modify('next friday')->week % 2 === 1) {
            return [
                'start' => $now->clone()->subDays(14),
                'end' => $now,
            ];
        }
    }
}
