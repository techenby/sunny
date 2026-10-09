<?php

use App\Livewire\Forms\Kiosk\SettingsForm;
use App\Livewire\Traits\WithKioskTeam;
use App\Models\KioskDevice;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk-configure')] class extends Component
{
    use WithKioskTeam;

    public SettingsForm $form;

    public function mount()
    {
        $this->form->load($this->team);
    }

    public function save()
    {
        $this->form->save();
    }

    public function forget(int $deviceId): void
    {
        KioskDevice::query()
            ->whereBelongsTo($this->team)
            ->whereKey($deviceId)
            ->delete();

        unset($this->pairedDevices);
    }

    /** @return Collection<int, KioskDevice> */
    #[Computed]
    public function pairedDevices(): Collection
    {
        return KioskDevice::query()
            ->whereBelongsTo($this->team)
            ->paired()
            ->orderByDesc('last_seen_at')
            ->get();
    }
};
