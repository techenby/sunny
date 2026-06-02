<?php

use App\Models\KioskDevice;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public const COOKIE_NAME = 'kiosk_device_uuid';

    public ?int $deviceId = null;

    public string $pairingCode = '';

    public ?string $expiresAt = null;

    public bool $expired = false;

    public function mount(): void
    {
        $device = $this->resolveDevice();

        if ($device->isPaired()) {
            $this->loginAndRedirect($device);

            return;
        }

        $this->syncFromDevice($device);
    }

    public function check(): mixed
    {
        $device = KioskDevice::query()->find($this->deviceId);

        if (! $device) {
            return $this->redirect(request()->fullUrl(), navigate: false);
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        if ($device->isPaired()) {
            return $this->loginAndRedirect($device);
        }

        if ($device->expires_at && $device->expires_at->isPast()) {
            $this->rotateCode($device);
        }

        $this->syncFromDevice($device);

        return null;
    }

    #[Computed]
    public function qrSvg(): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(280),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString(route('kiosk.pair', ['code' => $this->pairingCode]));
    }

    protected function resolveDevice(): KioskDevice
    {
        $uuid = request()->cookie(self::COOKIE_NAME);

        if ($uuid) {
            $device = KioskDevice::query()->where('uuid', $uuid)->first();

            if ($device && ($device->isPaired() || ! $device->expires_at?->isPast())) {
                return $device;
            }

            if ($device) {
                $this->rotateCode($device);

                return $device->fresh();
            }
        }

        return $this->createDevice();
    }

    protected function createDevice(): KioskDevice
    {
        $attempts = 0;

        do {
            $attempts++;
            try {
                $device = KioskDevice::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'pairing_code' => KioskDevice::generatePairingCode(),
                    'user_agent' => Str::limit((string) request()->userAgent(), 250, ''),
                    'last_ip' => request()->ip(),
                    'expires_at' => now()->addMinutes(KioskDevice::PAIRING_TTL_MINUTES),
                ]);

                Cookie::queue(Cookie::make(
                    self::COOKIE_NAME,
                    $device->uuid,
                    60 * 24 * 30,
                    '/',
                    null,
                    request()->isSecure(),
                    true,
                    false,
                    'lax',
                ));

                return $device;
            } catch (QueryException $e) {
                throw_if($attempts >= 5, $e);
            }
        } while (true);
    }

    protected function rotateCode(KioskDevice $device): void
    {
        $attempts = 0;

        do {
            $attempts++;
            try {
                $device->forceFill([
                    'pairing_code' => KioskDevice::generatePairingCode(),
                    'expires_at' => now()->addMinutes(KioskDevice::PAIRING_TTL_MINUTES),
                ])->save();

                return;
            } catch (QueryException $e) {
                throw_if($attempts >= 5, $e);
            }
        } while (true);
    }

    protected function syncFromDevice(KioskDevice $device): void
    {
        $this->deviceId = $device->id;
        $this->pairingCode = (string) $device->pairing_code;
        $this->expiresAt = $device->expires_at?->toIso8601String();
        $this->expired = false;
        unset($this->qrSvg);
    }

    protected function loginAndRedirect(KioskDevice $device): mixed
    {
        Auth::login($device->user);
        session()->regenerate(true);
        $device->user->switchTeam($device->team);
        session(['kiosk_device_id' => $device->id]);

        return $this->redirect(
            route('kiosk.calendar', ['current_team' => $device->team->slug]),
            navigate: false,
        );
    }
};
