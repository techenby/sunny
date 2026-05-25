<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Kiosk;

use App\Actions\Kiosk\CreateFeed;
use App\Actions\Kiosk\UpdateFeed;
use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CalendarFeedForm extends Form
{
    public ?CalendarFeed $editingFeed = null;

    public string $name = '';

    public string $url = '';

    public string $color = CalendarColor::Blue->value;

    public function load(CalendarFeed $feed): void
    {
        $this->fill([
            'editingFeed' => $feed,
            'name' => $feed->name,
            'url' => $feed->url,
            'color' => $feed->color->value,
        ]);
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingFeed) {
            (new UpdateFeed)->handle($this->editingFeed, $data);
        } else {
            (new CreateFeed)->handle(Auth::user()->currentTeam, $data);
        }

        $this->reset();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url'],
            'color' => ['required', Rule::enum(CalendarColor::class)],
        ];
    }
}
