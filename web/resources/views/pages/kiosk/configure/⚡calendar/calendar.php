<?php

use App\Livewire\Forms\Kiosk\CalendarFeedForm;
use App\Livewire\Traits\WithKioskTeam;
use App\Models\CalendarFeed;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk-configure')] class extends Component
{
    use WithKioskTeam;

    public CalendarFeedForm $form;

    #[Computed]
    public function feeds()
    {
        return $this->team->calendarFeeds()->orderBy('name')->get();
    }

    public function delete(int $id): void
    {
        $feed = $this->feeds->firstWhere('id', $id);

        $this->authorize('delete', $feed);

        $feed->delete();

        unset($this->feeds);
        Flux::toast(variant: 'success', text: __('Calendar feed removed.'));
    }

    public function edit(int $id): void
    {
        $feed = $this->feeds->firstWhere('id', $id);

        $this->authorize('update', $feed);

        $this->form->load($feed);
        $this->modal('feed-form')->show();
    }

    public function save(): void
    {
        if ($this->form->editingFeed) {
            $this->authorize('update', $this->form->editingFeed);
        } else {
            $this->authorize('create', CalendarFeed::class);
        }

        $this->form->save($this->team);
        $this->modal('feed-form')->close();
        unset($this->feeds);
    }
};
