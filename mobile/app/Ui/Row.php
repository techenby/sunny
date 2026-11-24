<?php

namespace App\Ui;

class Row
{
    /**
     * @param  'none'|'box'|'number'|'photo'  $leading
     */
    public static function inset(string $leading): string
    {
        return match ($leading) {
            'box' => 'ml-[72]',
            'number' => 'ml-[52]',
            'photo' => 'ml-[96]',
            default => 'ml-4',
        };
    }

    /**
     * @param  'none'|'box'|'number'|'photo'  $leading
     */
    public static function gap(string $leading): string
    {
        return $leading === 'number' ? 'gap-3' : 'gap-4';
    }
}
