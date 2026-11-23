<?php

namespace App\Support;

use Native\Mobile\Facades\Network;

class Offline
{
    public const string TITLE = 'You’re offline';

    public const string ADVICE = 'Try again when you’re connected.';

    public static function message(): string
    {
        return self::TITLE.'. '.self::ADVICE;
    }

    public static function now(): bool
    {
        return Network::status()?->connected === false;
    }
}
