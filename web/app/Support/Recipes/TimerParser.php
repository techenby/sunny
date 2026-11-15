<?php

declare(strict_types=1);

namespace App\Support\Recipes;

final class TimerParser
{
    /** @return list<array{seconds: int}> */
    public static function parse(string $text): array
    {
        $number = Quantity::PATTERN;
        $pattern = "/(?<![\w\/.])({$number})(?:\s*(?:-|–|—|\s+to\s+|\s+or\s+)\s*{$number})?[\s-]*(hours?|hrs?|minutes?|mins?|seconds?|secs?)\b/iu";

        if (! preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $timers = [];
        $previous = null;
        $last = -1;

        foreach ($matches as $match) {
            $scale = match (mb_strtolower($match[2][0])[0]) {
                'h' => 3600,
                'm' => 60,
                default => 1,
            };
            $seconds = (int) round((Quantity::toFloat($match[1][0]) ?? 0) * $scale);
            $start = $match[0][1];
            $end = $start + strlen($match[0][0]);

            $continues = $previous !== null
                && isset($timers[$last])
                && $scale < $previous['scale']
                && preg_match('/^\s*(?:and\s+|,\s*)?$/i', substr($text, $previous['end'], $start - $previous['end'])) === 1;

            if ($continues) {
                $timers[$last]['seconds'] += $seconds;
            } elseif ($seconds > 0) {
                $timers[] = ['seconds' => $seconds];
                $last++;
            }

            $previous = ['scale' => $scale, 'end' => $end];
        }

        return $timers;
    }
}
