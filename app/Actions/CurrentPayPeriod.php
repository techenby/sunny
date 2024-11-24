<?php

namespace App\Actions;

class CurrentPayPeriod
{
    public function __invoke()
    {
        $now = now();

        if ($now->isFriday() && $now->week % 2 === 1) {
            return [
                'start' => $now->format('Y-m-d'),
                'end' => $now->clone()->addDays(14)->format('Y-m-d'),
            ];
        } elseif ($now->modify('previous friday')->week % 2 === 1) {
            return [
                'start' => $now->format('Y-m-d'),
                'end' => $now->clone()->addDays(14)->format('Y-m-d'),
            ];
        } elseif ($now->modify('next friday')->week % 2 === 1) {
            return [
                'start' => $now->clone()->subDays(14)->format('Y-m-d'),
                'end' => $now->format('Y-m-d'),
            ];
        }
    }
}
