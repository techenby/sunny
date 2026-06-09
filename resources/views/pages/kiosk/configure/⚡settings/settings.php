<?php

use App\Livewire\Forms\Kiosk\SettingsForm;
use App\Models\KioskDevice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk-configure')] class extends Component
{
    public SettingsForm $form;

    public function mount()
    {
        $this->form->load(Auth::user()->currentTeam);
    }

    public function save()
    {
        $this->form->save();
    }

    public function forget(int $deviceId): void
    {
        KioskDevice::query()
            ->where('team_id', Auth::user()->current_team_id)
            ->whereKey($deviceId)
            ->delete();

        unset($this->pairedDevices);
    }

    /** @return Collection<int, KioskDevice> */
    #[Computed]
    public function pairedDevices(): Collection
    {
        return KioskDevice::query()
            ->where('team_id', Auth::user()->current_team_id)
            ->paired()
            ->orderByDesc('last_seen_at')
            ->get();
    }
};
