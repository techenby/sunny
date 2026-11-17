<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsQueuedChange;
use App\Enums\ChecklistType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Icons\Android;
use App\Icons\Ios;
use App\Models\PendingWrite;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;

class ListDetail extends Component
{
    use ChecksSunnySync;
    use ShowsQueuedChange;

    public string $newItem = '';

    public string $newItemUrl = '';

    public ?int $editingItemId = null;

    public string $editingName = '';

    public string $editingUrl = '';

    public string $linkError = '';

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
     * @return list<array{id: int, name: string, url: string|null, domain: string|null, completed: bool, error: string|null}>
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
            'domain' => $item['url'] !== null ? self::domainOf($item['url']) : null,
            'completed' => $item['completed_at'] !== null,
            'error' => $errors[$item['id']] ?? null,
        ])->all();
    }

    #[Computed]
    public function takesLinks(): bool
    {
        return ($this->checklist['type'] ?? null) === ChecklistType::Wishlist;
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
        $url = self::withScheme($this->newItemUrl);

        if ($this->checklist === null || $name === '') {
            return;
        }

        if (mb_strlen($name) > 255) {
            $this->error = 'The item is too long (255 characters max).';

            return;
        }

        if (($this->linkError = $this->problemWithLink($url)) !== '') {
            return;
        }

        $payload = ['checklist_id' => $this->checklist['id'], 'name' => $name, ...($url !== '' ? ['url' => $url] : [])];

        if ($this->change(fn (SunnyOutbox $outbox) => $outbox->queue('checklist_items', $this->checklist['team_id'], $payload))) {
            $this->newItem = '';
            $this->newItemUrl = '';
        }
    }

    public function submitNewItemUrl(string $url = ''): void
    {
        $this->newItemUrl = $url;
        $this->addItem();
    }

    public function updatedNewItemUrl(string $url): void
    {
        $this->linkError = $this->problemWithLink($url, typing: true);
    }

    public function clearNewItemUrl(): void
    {
        $this->newItemUrl = '';
        $this->linkError = '';
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
            $this->editingUrl = $item['url'] ?? '';
            $this->linkError = '';
            $this->error = '';
        }
    }

    public function saveItem(?string $text = null): void
    {
        $item = collect($this->items)->firstWhere('id', $this->editingItemId);
        $name = trim($text ?? $this->editingName);
        $url = self::withScheme($this->editingUrl);

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

        if (($this->linkError = $this->problemWithLink($url)) !== '') {
            return;
        }

        $changes = [
            ...($name !== $item['name'] ? ['name' => $name] : []),
            ...(($url !== '' ? $url : null) !== $item['url'] ? ['url' => $url !== '' ? $url : null] : []),
        ];

        if ($changes === [] || $this->change(fn (SunnyOutbox $outbox) => $outbox->queue('checklist_items', $this->checklist['team_id'], $changes, $item['id']))) {
            $this->cancelEdit();
        }
    }

    public function submitEditingUrl(string $url = ''): void
    {
        $this->editingUrl = $url;
        $this->saveItem();
    }

    public function updatedEditingUrl(string $url): void
    {
        $this->linkError = $this->problemWithLink($url, typing: true);
    }

    public function clearEditingUrl(): void
    {
        $this->editingUrl = '';
        $this->linkError = '';
    }

    public function removeLink(): void
    {
        $this->editingUrl = '';
        $this->saveItem();
    }

    public function cancelEdit(): void
    {
        $this->editingItemId = null;
        $this->editingName = '';
        $this->editingUrl = '';
        $this->linkError = '';
        $this->error = '';
    }

    public function removeItem(int $id): void
    {
        if (! collect($this->items)->contains('id', $id)) {
            return;
        }

        if ($this->editingItemId === $id) {
            $this->cancelEdit();
        }

        $this->change(fn (SunnyOutbox $outbox) => $outbox->delete('checklist_items', $this->checklist['team_id'], $id));
    }

    /**
     * @param  array{id: int, name: string, url: string|null}  $item
     * @return list<NavAction>
     */
    public function itemMenu(array $item): array
    {
        return array_values(array_filter([
            NavAction::make('list-item-'.$item['id'].'-edit')->label('Edit')->icon(ios: Ios::Pencil->value, android: Android::Edit->value)->press('editItem('.$item['id'].')'),
            $item['url'] !== null ? NavAction::make('list-item-'.$item['id'].'-open-link')->label('Open link')->icon(ios: Ios::ArrowUpRight->value, android: Android::OpenInNew->value)->press('openItemLink('.$item['id'].')') : null,
            NavAction::make('list-item-'.$item['id'].'-remove')->label('Remove')->icon(ios: Ios::Trash->value, android: Android::Delete->value)->press('removeItem('.$item['id'].')')->destructive(),
        ]));
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
        unset($this->checklist, $this->items, $this->takesLinks, $this->summary, $this->queuedChange);
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

    private static function domainOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::chopStart(Str::lower($host), 'www.') : $url;
    }

    private static function withScheme(string $url): string
    {
        $url = trim($url);

        if ($url === '' || (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url, $scheme) === 1 && ! str_contains($scheme[0], '.'))) {
            return $url;
        }

        return 'https://'.$url;
    }

    private function problemWithLink(string $url, bool $typing = false): string
    {
        $url = trim($url);

        if ($url === '' || ($typing && (str_starts_with('https://', Str::lower($url)) || str_starts_with('http://', Str::lower($url))))) {
            return '';
        }

        $url = self::withScheme($url);

        if (mb_strlen($url) > 2048) {
            return 'The link is too long (2048 characters max).';
        }

        $isWebAddress = Validator::make(['url' => $url], ['url' => 'url:http,https'])->passes()
            && ($typing || str_contains((string) parse_url($url, PHP_URL_HOST), '.'));

        return $isWebAddress ? '' : 'That’s not a web address. Try one like lego.com';
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
