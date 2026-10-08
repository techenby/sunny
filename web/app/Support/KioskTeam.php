<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\KioskDevice;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

class KioskTeam
{
    public static function inKioskSession(): bool
    {
        return session()->has('kiosk_device_id');
    }

    public static function device(): ?KioskDevice
    {
        $user = Auth::user();

        if (! $user || ! self::inKioskSession()) {
            return null;
        }

        return KioskDevice::query()
            ->paired()
            ->whereBelongsTo($user)
            ->with('team')
            ->find(session('kiosk_device_id'));
    }

    public static function resolve(): Team
    {
        if (self::inKioskSession()) {
            return self::device()?->team ?? abort(403);
        }

        $team = request()->route('current_team');

        if (is_string($team)) {
            $team = Team::query()->where('slug', $team)->first();
        }

        return $team ?? Auth::user()->currentTeam;
    }
}
