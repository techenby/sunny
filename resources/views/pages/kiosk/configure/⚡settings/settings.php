<?php

use App\Livewire\Forms\Kiosk\SettingsForm;
use Illuminate\Support\Facades\Auth;
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
};
