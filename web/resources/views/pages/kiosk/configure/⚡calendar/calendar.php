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

    #[Computed]
    public function failingFeeds()
    {
        return $this->feeds->filter->isFailing();
    }

    #[Computed]
    public function failingSummary(): ?string
    {
        $count = $this->failingFeeds->count();

        if ($count === 0) {
            return null;
        }

        $hosts = $this->failingFeeds->map(fn (CalendarFeed $feed) => parse_url($feed->url, PHP_URL_HOST))->unique();

        if ($count > 1 && $hosts->count() === 1 && $hosts->first()) {
            return __(':count feeds are failing, all from :host.', ['count' => $count, 'host' => $hosts->first()]);
        }

        return trans_choice(':count feed is failing.|:count feeds are failing.', $count);
    }

    #[Computed]
    public function sharedFailingError(): ?string
    {
        $errors = $this->failingFeeds->pluck('last_error')->filter()->unique();

        return $errors->count() === 1 ? $errors->first() : null;
    }

    public function delete(int $id): void
    {
        $feed = $this->feeds->firstWhere('id', $id);

        $this->authorize('delete', $feed);

        $feed->delete();

        unset($this->feeds, $this->failingFeeds, $this->failingSummary, $this->sharedFailingError);
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
        unset($this->feeds, $this->failingFeeds, $this->failingSummary, $this->sharedFailingError);
    }
};
