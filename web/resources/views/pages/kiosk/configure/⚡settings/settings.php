<?php

use App\Livewire\Forms\Kiosk\SettingsForm;
use App\Livewire\Traits\WithKioskTeam;
use App\Models\KioskDevice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
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
        $this->authorize('update', $this->team);

        KioskDevice::query()
            ->whereBelongsTo($this->team)
            ->whereKey($deviceId)
            ->delete();

        unset($this->pairedDevices);
    }

    #[Computed]
    public function canUpdateTeam(): bool
    {
        return Gate::allows('update', $this->team);
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
