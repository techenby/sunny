<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Models\PendingWrite;
use App\Models\Routine;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class ManageRoutines extends NativeComponent
{
    use ChecksSunnySync;

    public string $search = '';

    /**
     * @return list<array{id: int, name: string, summary: string, timeOfDay: string, status: string|null}>
     */
    #[Computed]
    public function routines(): array
    {
        $writes = PendingWrite::query()->forCurrentServer()->where('resource', 'routines')->get()->keyBy('record_id');
        $deleting = app(SunnyOutbox::class)->pendingRoutineDeletes();

        return Routine::forActiveTeam()->get()
            ->filter(fn (Routine $routine): bool => $this->search === '' || Str::contains($routine->name, $this->search, ignoreCase: true))
            ->sortBy(fn (Routine $routine): array => [$routine->time_of_day->sortOrder(), $routine->name])
            ->map(fn (Routine $routine): array => [
                'id' => $routine->id,
                'name' => $routine->name,
                'summary' => collect([$routine->time_of_day->label(), $routine->scheduleSummary(), $routine->is_active ? null : 'Paused'])->filter()->implode(' · '),
                'timeOfDay' => $routine->time_of_day->value,
                'status' => match (true) {
                    ! $writes->has($routine->id) => null,
                    $writes[$routine->id]->error !== null => 'Not saved to Sunny',
                    $deleting->contains($routine->id) => 'Deleting',
                    default => 'Syncing with Sunny',
                },
            ])
            ->values()
            ->all();
    }

    public function updateSearch(string $query): void
    {
        $this->search = trim($query);
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status === 'finished') {
            $this->refreshLocalSyncedData();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->routines);
    }

    public function render(): View
    {
        return view('native.manage-routines');
    }
}
