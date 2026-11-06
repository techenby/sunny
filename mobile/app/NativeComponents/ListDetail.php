<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsQueuedChange;
use App\Enums\ChecklistType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\PendingWrite;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;

class ListDetail extends NativeComponent
{
    use ChecksSunnySync;
    use ShowsQueuedChange;

    public string $newItem = '';

    public ?int $editingItemId = null;

    public string $editingName = '';

    public string $error = '';

    /**
     * @return array{id: int, team_id: int, user_id: int|null, type: ChecklistType, name: string, created_at: string, updated_at: string}|null
     */
    #[Computed]
    public function checklist(): ?array
    {
        return Lists::find((int) $this->param('id'));
    }

    /**
     * @return list<array{id: int, name: string, url: string|null, completed: bool, error: string|null}>
     */
    #[Computed]
    public function items(): array
    {
        if ($this->checklist === null) {
            return [];
        }

        $items = collect(Lists::itemsOf($this->checklist['id']));
        $errors = PendingWrite::query()->forCurrentServer()->where('resource', 'checklist_items')
            ->whereIn('record_id', $items->pluck('id'))->whereNotNull('error')->pluck('error', 'record_id');

        return $items->map(fn (array $item): array => [
            'id' => $item['id'],
            'name' => $item['name'],
            'url' => $item['url'],
            'completed' => $item['completed_at'] !== null,
            'error' => $errors[$item['id']] ?? null,
        ])->all();
    }

    #[Computed]
    public function summary(): string
    {
        $label = $this->checklist['type']->label();

        return $this->items === [] ? $label : $label.' · '.collect($this->items)->where('completed', true)->count().' of '.count($this->items).' done';
    }

    public function addItem(string $text = ''): void
    {
        $name = trim($text !== '' ? $text : $this->newItem);

        if ($this->checklist === null || $name === '') {
            return;
        }

        if (mb_strlen($name) > 255) {
            $this->error = 'The item is too long (255 characters max).';

            return;
        }

        if ($this->change(fn (SunnyOutbox $outbox) => $outbox->queue('checklist_items', $this->checklist['team_id'], ['checklist_id' => $this->checklist['id'], 'name' => $name]))) {
            $this->newItem = '';
        }
    }

    public function toggleItem(int $id): void
    {
        $item = collect($this->items)->firstWhere('id', $id);

        if ($item !== null) {
            $this->change(fn (SunnyOutbox $outbox) => $outbox->queue('checklist_items', $this->checklist['team_id'], ['completed' => ! $item['completed']], $id));
        }
    }

    public function openItemLink(int $id): void
    {
        $url = collect($this->items)->firstWhere('id', $id)['url'] ?? null;

        if ($url !== null) {
            Browser::inApp($url);
        }
    }

    public function editItem(int $id): void
    {
        $item = collect($this->items)->firstWhere('id', $id);

        if ($item !== null) {
            $this->editingItemId = $id;
            $this->editingName = $item['name'];
            $this->error = '';
        }
    }

    public function saveItem(?string $text = null): void
    {
        $item = collect($this->items)->firstWhere('id', $this->editingItemId);
        $name = trim($text ?? $this->editingName);

        if ($item === null) {
            $this->cancelEdit();

            return;
        }

        if ($name === '') {
            $this->error = 'Give the item a name.';

            return;
        }

        if (mb_strlen($name) > 255) {
            $this->error = 'The item is too long (255 characters max).';

            return;
        }

        if ($name === $item['name'] || $this->change(fn (SunnyOutbox $outbox) => $outbox->queue('checklist_items', $this->checklist['team_id'], ['name' => $name], $item['id']))) {
            $this->cancelEdit();
        }
    }

    public function cancelEdit(): void
    {
        $this->editingItemId = null;
        $this->editingName = '';
        $this->error = '';
    }

    public function removeItem(int $id): void
    {
        if (collect($this->items)->contains('id', $id)) {
            $this->change(fn (SunnyOutbox $outbox) => $outbox->delete('checklist_items', $this->checklist['team_id'], $id));
        }
    }

    public function confirmDeleteList(): void
    {
        if ($this->checklist === null) {
            return;
        }

        Dialog::alert('Delete list?', '“'.$this->checklist['name'].'” and everything on it will be deleted for everyone on your team.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Delete', 'style' => 'destructive'],
        ])->id('delete-list')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'delete-list' && $label === 'Delete') {
            $this->deleteList();
        } elseif ($id === 'discard-queued-change' && $label === 'Discard') {
            $this->discardQueuedChange();
        }
    }

    public function deleteList(): void
    {
        if ($this->checklist !== null && $this->change(fn (SunnyOutbox $outbox) => $outbox->delete('checklists', $this->checklist['team_id'], $this->checklist['id']))) {
            $this->back();
        }
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
        unset($this->checklist, $this->items, $this->summary, $this->queuedChange);
    }

    protected function queuedResource(): string
    {
        return 'checklists';
    }

    protected function queuedRecordId(): ?int
    {
        return $this->checklist['id'] ?? null;
    }

    public function render(): View
    {
        return view('native.list-detail');
    }

    private function change(callable $callback): bool
    {
        try {
            $callback(app(SunnyOutbox::class));
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to save on this phone. Try again.';

            return false;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->error = '';
        $this->refreshLocalSyncedData();

        return true;
    }
}
