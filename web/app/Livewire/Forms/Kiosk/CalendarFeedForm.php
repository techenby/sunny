<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Kiosk;

use App\Actions\Kiosk\CreateFeed;
use App\Actions\Kiosk\UpdateFeed;
use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function save(Team $team): void
    {
        $data = $this->validate();

        if ($this->editingFeed) {
            (new UpdateFeed)->handle($this->editingFeed, $data);
        } else {
            try {
                (new CreateFeed)->handle($team, $data);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(["{$this->getPropertyName()}.url" => $exception->getMessage()]);
            }
        }

        $this->reset();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'color' => ['required', Rule::enum(CalendarColor::class)],
        ];
    }
}
