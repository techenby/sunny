<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use ZXN\NativeWatch\Core\Laravel\NativeWatch;

class CrashReports
{
    private const ASKED = 'crash-reports-asked';

    public function available(): bool
    {
        return (bool) config('nativewatch.enabled') && filled(config('nativewatch.key'));
    }

    public function sharing(): bool
    {
        return $this->available() && NativeWatch::hasConsent();
    }

    public function share(bool $share): bool
    {
        return NativeWatch::setConsent($share);
    }

    public function shouldAsk(): bool
    {
        return $this->available() && ! Storage::exists(self::ASKED) && ! $this->sharing();
    }

    public function markAsked(): void
    {
        Storage::put(self::ASKED, now()->toIso8601String());
    }
}
