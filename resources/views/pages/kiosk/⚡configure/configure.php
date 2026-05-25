<?php

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $editingFeedId = null;

    public string $feedName = '';

    public string $feedUrl = '';

    public string $feedColor = '#2563eb';

    #[Computed]
    public function feeds()
    {
        return Auth::user()
            ->currentTeam
            ->calendarFeeds()
            ->orderBy('name')
            ->get();
    }

    public function saveFeed(): void
    {
        $validated = $this->validate([
            'feedName' => ['required', 'string', 'max:255'],
            'feedUrl' => ['required', 'url', 'max:2048'],
            'feedColor' => ['required', 'string', Rule::in($this->calendarColorValues())],
        ]);

        $attributes = [
            'name' => $validated['feedName'],
            'url' => $validated['feedUrl'],
            'color' => $validated['feedColor'],
        ];

        if ($this->editingFeedId) {
            $this->feedQuery()
                ->whereKey($this->editingFeedId)
                ->firstOrFail()
                ->update($attributes);

            Flux::toast(variant: 'success', text: __('Calendar feed updated.'));
        } else {
            $this->feedQuery()->create($attributes);

            Flux::toast(variant: 'success', text: __('Calendar feed added.'));
        }

        unset($this->feeds);

        $this->resetFeedForm();
    }

    public function editFeed(int $feedId): void
    {
        $feed = $this->feedQuery()
            ->whereKey($feedId)
            ->firstOrFail();

        $this->editingFeedId = $feed->id;
        $this->feedName = $feed->name;
        $this->feedUrl = $feed->url;
        $this->feedColor = $feed->color;

        $this->resetValidation();
    }

    public function deleteFeed(int $feedId): void
    {
        $this->feedQuery()
            ->whereKey($feedId)
            ->firstOrFail()
            ->delete();

        if ($this->editingFeedId === $feedId) {
            $this->resetFeedForm();
        }

        unset($this->feeds);

        Flux::toast(variant: 'success', text: __('Calendar feed removed.'));
    }

    public function resetFeedForm(): void
    {
        $this->reset(['editingFeedId', 'feedName', 'feedUrl']);
        $this->feedColor = CalendarColor::Blue->value;
        $this->resetValidation();
    }

    /** @return array<int, CalendarColor> */
    public function calendarColors(): array
    {
        return CalendarColor::cases();
    }

    private function feedQuery()
    {
        return Auth::user()->currentTeam->calendarFeeds();
    }

    /** @return array<int, string> */
    private function calendarColorValues(): array
    {
        return array_map(
            fn (CalendarColor $color): string => $color->value,
            CalendarColor::cases(),
        );
    }
};
