<?php

use App\Models\KioskDevice;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public string $code = '';

    public KioskDevice $device;

    public string $name = '';

    public ?int $teamId = null;

    public bool $paired = false;

    public bool $expired = false;

    public function mount(string $code): void
    {
        $device = KioskDevice::query()
            ->where('pairing_code', $code)
            ->pending()
            ->first();

        if (! $device) {
            $this->code = $code;
            $this->expired = true;
            $this->device = new KioskDevice;

            return;
        }

        $this->code = $code;
        $this->device = $device;

        $teams = $this->teams;
        if ($teams->count() === 1) {
            $this->teamId = $teams->first()->id;
        } elseif (Auth::user()->current_team_id && $teams->contains('id', Auth::user()->current_team_id)) {
            $this->teamId = Auth::user()->current_team_id;
        }
    }

    /** @return Collection<int, Team> */
    #[Computed]
    public function teams(): Collection
    {
        return Auth::user()->teams()->orderBy('name')->get();
    }

    #[Computed]
    public function deviceLabel(): string
    {
        $ua = (string) $this->device->user_agent;

        if ($ua === '') {
            return __('Unknown device');
        }

        return match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android device',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows PC',
            str_contains($ua, 'Linux') => 'Linux device',
            default => __('Web browser'),
        };
    }

    public function approve(): void
    {
        if ($this->expired) {
            return;
        }

        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'teamId' => ['required', 'integer', 'in:' . $this->teams->pluck('id')->implode(',')],
        ]);

        $name = trim((string) $validated['name']) ?: null;

        $affected = KioskDevice::query()
            ->whereKey($this->device->id)
            ->whereNull('paired_at')
            ->where('expires_at', '>', now())
            ->where('pairing_code', $this->code)
            ->update([
                'user_id' => Auth::id(),
                'team_id' => $validated['teamId'],
                'name' => $name,
                'paired_at' => now(),
                'expires_at' => null,
                'pairing_code' => null,
                'updated_at' => now(),
            ]);

        if ($affected !== 1) {
            $this->expired = true;

            return;
        }

        $this->paired = true;
    }

    public function reject(): void
    {
        if ($this->expired) {
            return;
        }

        KioskDevice::query()
            ->whereKey($this->device->id)
            ->whereNull('paired_at')
            ->delete();

        $this->expired = true;
    }
};
