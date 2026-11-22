<?php

namespace App\NativeComponents;

use App\Actions\DuplicateItem;
use App\Concerns\ChecksSunnySync;
use App\Concerns\ChoosesInventoryParent;
use App\Concerns\MovesItemToTeam;
use App\Concerns\SelectsInventoryItems;
use App\Concerns\ShowsQueuedChange;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Icons\Android;
use App\Icons\Ios;
use App\Models\ItemPhoto;
use App\Models\PendingWrite;
use App\Models\Team;
use App\Support\Toast;
use App\Support\ToastAction;
use App\Ui\Menu;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Elements\TopBarAction;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class InventoryItemDetail extends Component
{
    use ChecksSunnySync;
    use ChoosesInventoryParent {
        selectParent as protected chooseParent;
        selectTopLevel as protected chooseTopLevel;
    }
    use MovesItemToTeam;
    use SelectsInventoryItems;
    use ShowsQueuedChange;

    public string $error = '';

    public int $photoIndex = 0;

    public bool $showCopies = false;

    public int $copies = 1;

    public ?string $copiesProblem = null;

    public string $copiesError = '';

    public function mount(): void
    {
        $this->showMovedToTeam();
    }

    public function onResume(): void
    {
        $this->refreshLocalSyncedData();
        $this->showMovedToTeam();
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    #[Computed]
    public function item(): ?array
    {
        return Inventory::find((int) $this->param('id'));
    }

    /**
     * @return array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}|null
     */
    #[Computed]
    public function parent(): ?array
    {
        $parentId = $this->item['parent_id'] ?? null;

        return $parentId === null ? null : Inventory::find($parentId);
    }

    /**
     * Every container this item is inside, from the top level down to its direct parent.
     *
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, created_at: string, updated_at: string}>
     */
    #[Computed]
    public function path(): array
    {
        $path = [];
        $ancestor = $this->parent;

        while ($ancestor !== null && count($path) < 50) {
            array_unshift($path, $ancestor);
            $ancestor = $ancestor['parent_id'] === null ? null : Inventory::find($ancestor['parent_id']);
        }

        return $path;
    }

    /**
     * @return list<array{id: int, parent_id: int|null, type: ItemType, name: string, metadata: array<string, string>|null, photo_url: string|null, thumb_path: string|null, created_at: string, updated_at: string, children_count: int}>
     */
    #[Computed]
    public function children(): array
    {
        return $this->item === null ? [] : Inventory::childrenOf($this->item['id']);
    }

    /** @return list<array{number: int, url: string, thumb: string}> */
    #[Computed]
    public function photos(): array
    {
        if ($this->item === null) {
            return [];
        }

        $photos = ItemPhoto::query()->forCurrentServer()->where('item_id', $this->item['id'])->orderBy('position')->orderBy('id')->get()
            ->filter(fn (ItemPhoto $photo): bool => $photo->url !== null)
            ->values()
            ->map(fn (ItemPhoto $photo, int $index): array => ['number' => $index + 1, 'url' => $photo->url, 'thumb' => (string) $photo->thumbnail()])
            ->all();

        return $photos !== [] || ($this->item['photo_url'] ?? null) === null
            ? $photos
            : [['number' => 1, 'url' => $this->item['photo_url'], 'thumb' => $this->item['thumb_path'] ?? $this->item['photo_url']]];
    }

    public function showPhoto(int $index): void
    {
        $this->photoIndex = max(0, min($index, count($this->photos) - 1));
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function starterStorage(): array
    {
        if ($this->item === null || $this->item['type'] !== ItemType::Location || $this->children !== []) {
            return [];
        }

        return match (mb_strtolower(trim($this->item['name']))) {
            'garage' => ['Camping box', 'Tool chest', 'Holiday decorations', 'Paint shelf'],
            'kitchen pantry' => ['Top shelf', 'Baking box', 'Snack basket', 'Spice rack'],
            'hall closet' => ['Top shelf', 'Shoe rack', 'Gift wrap box', 'Cleaning caddy'],
            'basement' => ['Storage shelves', 'Holiday decorations', 'Keepsake box', 'Tool bench'],
            'attic' => ['Holiday decorations', 'Keepsake box', 'Winter clothes box', 'Baby clothes box'],
            'laundry room' => ['Cleaning shelf', 'Linen shelf', 'Sewing box', 'Detergent cabinet'],
            'shed' => ['Garden tools rack', 'Seed box', 'Paint shelf', 'Tool chest'],
            "kids' rooms" => ['Toy box', 'Bookshelf', 'Art supplies box', 'Dresser'],
            'office' => ['Desk drawer', 'Filing cabinet', 'Cable box', 'Bookshelf'],
            default => ['Top shelf', 'Storage box', 'Drawer', 'Cabinet'],
        };
    }

    public function addStarterStorage(int $index): void
    {
        $name = $this->starterStorage[$index] ?? null;

        if ($name === null) {
            return;
        }

        try {
            app(SunnyOutbox::class)->queue('items', $this->item['team_id'], [
                'name' => $name,
                'type' => ItemType::Bin->value,
                'parent_id' => $this->item['id'],
                'metadata' => null,
            ]);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to save on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->error = '';
        $this->refreshLocalSyncedData();
        Toast::success('Added '.$name.' to '.$this->item['name']);
    }

    #[Computed]
    public function teamId(): ?int
    {
        return $this->item['team_id'] ?? null;
    }

    /**
     * @return list<NavAction|TopBarAction>
     */
    public function moreMenu(): array
    {
        return array_values(array_filter([
            NavAction::make('item-print-label')->label('Print label')->icon(ios: Ios::Qrcode->value, android: Android::QrCode2->value)->press('showQrCode'),
            NavAction::make('item-make-copies')->label('Make copies')->icon(ios: Ios::SquareOnSquare->value, android: Android::ContentCopy->value)->press('openCopies'),
            $this->teamMoveTeams() === [] ? null : NavAction::make('item-move-to-team')->label('Move to another team')->icon(ios: Ios::ArrowLeftArrowRight->value, android: Android::SwapHoriz->value)->press('openTeamMove'),
            Menu::delete('delete-item', 'Delete '.mb_strtolower($this->item['type']->label()), 'confirmDeleteItem', 'Delete '.$this->item['name']),
        ]));
    }

    public function openCopies(): void
    {
        $this->copies = 1;
        $this->copiesProblem = null;
        $this->copiesError = '';
        $this->showCopies = true;
    }

    public function closeCopies(): void
    {
        $this->showCopies = false;
        $this->copiesProblem = null;
        $this->copiesError = '';
    }

    public function changeCopies(int $by): void
    {
        $this->copies = max(1, min(DuplicateItem::MAX_COPIES, $this->copies + $by));
    }

    public function makeCopies(): void
    {
        if ($this->item === null) {
            return;
        }

        $this->copiesProblem = null;
        $this->copiesError = '';

        if ($this->hasUnsentChanges()) {
            try {
                app(SunnySyncCoordinator::class)->sync();
            } catch (FatalRequestException) {
                $this->copiesProblem = 'offline';

                return;
            } catch (AuthenticationException|RequestException|JsonException|ValidationException|RuntimeException) {
            }

            $this->refreshLocalSyncedData();

            if ($this->item === null || $this->hasUnsentChanges()) {
                $this->copiesProblem = 'unsent';

                return;
            }
        }

        $team = Team::query()->where('server', SunnyStore::server())->find($this->item['team_id']);

        if (! $team?->slug) {
            $this->copiesFailed('Sync with Sunny before making copies.');

            return;
        }

        try {
            $made = app(DuplicateItem::class)->handle($team, $this->item['id'], $this->copies);
        } catch (FatalRequestException) {
            $this->copiesProblem = 'offline';

            return;
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $this->copiesFailed(match ($response->status()) {
                403 => 'You no longer have permission to add things to this team.',
                404 => $this->item['name'].' is no longer on Sunny.',
                422 => collect($response->json('errors') ?? [])->flatten()->first() ?? 'Sunny couldn’t make copies.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'Sunny couldn’t make copies right now.',
            });
            app(SunnySyncCoordinator::class)->dispatch();

            return;
        } catch (AuthenticationException|RuntimeException) {
            $this->copiesFailed('Unable to read your saved login. Unlock your device and try again.');

            return;
        } catch (JsonException) {
            $made = 0;
        }

        if ($made === 0) {
            $this->copiesFailed('Sunny didn’t confirm the copies.');
            app(SunnySyncCoordinator::class)->dispatch();

            return;
        }

        $this->showCopies = false;
        $this->refreshLocalSyncedData();
        Toast::success(trans_choice(':count copy made|:count copies made', $made), action: new ToastAction('View', 'view-copies', ['item' => $this->item['id']]));
    }

    /**
     * @param  array{item?: int|float|string}  $data
     */
    #[On(Toast::ACTION_PRESSED, when: ['actionId' => 'view-copies'])]
    public function viewCopies(array $data): void
    {
        if ($this->item === null || ! is_numeric($data['item'] ?? null) || (int) $data['item'] !== $this->item['id']) {
            return;
        }

        if ($this->parent === null) {
            $this->navigate('/inventory');

            return;
        }

        $this->openParent();
    }

    public function copiesPlace(): string
    {
        return match (true) {
            $this->parent !== null => 'in '.$this->parent['name'],
            $this->item['type'] === ItemType::Location => 'with the places in your home',
            default => 'in “Not in a place yet”',
        };
    }

    /**
     * @return array{title: string, message: string}
     */
    public function copiesNote(): array
    {
        [$title, $message] = match ($this->copiesProblem) {
            'offline' => ['You’re offline', 'Making copies needs a connection, since Sunny copies the photos for you.'],
            'unsent' => ['Still sending your changes', 'Sunny copies what it has, so your latest changes need to reach it first.'],
            default => ['Couldn’t make copies', $this->copiesError],
        };

        return ['title' => $title, 'message' => $message.' Nothing’s been copied yet.'];
    }

    protected function copiesFailed(string $message): void
    {
        $this->copiesProblem = 'failed';
        $this->copiesError = $message;
    }

    protected function hasUnsentChanges(): bool
    {
        return $this->item['id'] < 0
            || $this->queuedChange !== null
            || PendingWrite::query()->forCurrentServer()->where('resource', 'item_photos')->get()
                ->contains(fn (PendingWrite $write): bool => ($write->payload['item_id'] ?? null) === $this->item['id']);
    }

    /**
     * @return list<NavAction>
     */
    public function addMenu(): array
    {
        return [
            NavAction::make('item-add-child')->label('Add one thing')->icon(ios: Ios::Plus->value, android: Android::Add->value)->press('addChild'),
            NavAction::make('item-scan-children')->label('Bulk add')->icon(ios: Ios::CameraViewfinder->value, android: Android::DocumentScanner->value)->press('scanChildren'),
        ];
    }

    public function startMove(): void
    {
        if ($this->item === null) {
            return;
        }

        $this->movingSelected = false;
        unset($this->parentChoices, $this->parentPickerRows, $this->selectedParent, $this->browsedParent, $this->parentTrail);
        $this->parentId = $this->parentChoice($this->item['parent_id']) === null ? null : $this->item['parent_id'];
        $this->openParentPicker();
    }

    public function selectParent(int $id): void
    {
        $this->chooseParent($id);

        if ($this->movingSelected) {
            $this->moveSelected();

            return;
        }

        $this->moveItem();
    }

    public function selectTopLevel(): void
    {
        $this->chooseTopLevel();

        if ($this->movingSelected) {
            $this->moveSelected();

            return;
        }

        $this->moveItem();
    }

    public function editPhoto(): void
    {
        $this->navigate('/inventory/'.$this->item['id'].'/edit');
    }

    public function addChild(): void
    {
        $this->navigate('/inventory/create', ['parent' => $this->item['id']]);
    }

    public function showQrCode(): void
    {
        $this->navigate('/inventory/'.$this->item['id'].'/qr-code');
    }

    public function scanChildren(): void
    {
        $this->navigate('/inventory/scan', ['parent' => $this->item['id']]);
    }

    /**
     * Follow the "Inside" row up to the containing item.
     *
     * Rows pass the screen they were tapped from as `from` navigation data, so
     * when the parent is the screen directly below this one we pop back to the
     * live instance instead of pushing a second copy of it onto the stack —
     * otherwise the back button walks the user through the duplicate.
     */
    public function openParent(): void
    {
        $parent = $this->parent;

        if ($parent === null) {
            return;
        }

        if ($this->data('from') === $parent['id']) {
            $this->back();

            return;
        }

        $this->navigate('/inventory/'.$parent['id'], ['from' => $this->item['id']]);
    }

    public function confirmDeleteItem(): void
    {
        if ($this->item === null) {
            return;
        }

        $count = count($this->children);
        $message = '“'.$this->item['name'].'” will be deleted for everyone on your team.';

        if ($count > 0) {
            $message .= ' '.trans_choice('The :count thing inside it will move to “Not in a place yet”.|The :count things inside it will move to “Not in a place yet”.', $count);
        }

        Dialog::alert('Delete '.$this->item['name'].'?', $message, [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Delete', 'style' => 'destructive'],
        ])->id('delete-item')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'delete-item' && $label === 'Delete') {
            $this->deleteItem();
        } elseif ($id === 'delete-selected' && $label === 'Delete') {
            $this->deleteSelected();
        } elseif ($id === 'discard-queued-change' && $label === 'Discard') {
            $this->discardQueuedChange();
        }
    }

    public function deleteItem(): void
    {
        if ($this->item === null) {
            return;
        }

        try {
            app(SunnyOutbox::class)->delete('items', $this->item['team_id'], $this->item['id']);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to delete on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->back();
    }

    protected function moveItem(): void
    {
        if ($this->item === null || $this->parentId === $this->item['parent_id']) {
            return;
        }

        try {
            app(SunnyOutbox::class)->queue('items', $this->item['team_id'], [
                'name' => $this->item['name'],
                'type' => $this->item['type']->value,
                'parent_id' => $this->parentId,
                'metadata' => $this->item['metadata'],
            ], $this->item['id']);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to move it on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->error = '';
        $this->refreshLocalSyncedData();

        Toast::success(match (true) {
            $this->parent !== null => 'Moved to '.$this->parent['name'],
            $this->item['type'] === ItemType::Location => 'Now one of the places in your home',
            default => 'Moved to “Not in a place yet”',
        });
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
        unset($this->item, $this->photos, $this->parent, $this->path, $this->children, $this->starterStorage, $this->queuedChange, $this->parentChoices, $this->selectedParent, $this->browsedParent, $this->parentPickerRows, $this->parentTrail, $this->selectedItems, $this->selectedAll, $this->selectedMoveCopy);
    }

    /**
     * Jump to any container in the path. The direct parent goes through {@see openParent()} so it can pop back instead of pushing a duplicate.
     */
    public function openAncestor(int $id): void
    {
        if ($id === ($this->parent['id'] ?? null)) {
            $this->openParent();

            return;
        }

        if (in_array($id, array_column($this->path, 'id'), true)) {
            $this->navigate('/inventory/'.$id, ['from' => $this->item['id']]);
        }
    }

    /**
     * @return list<int>
     */
    protected function unselectableParentIds(): array
    {
        if ($this->movingSelected) {
            return $this->selectedWithContentsIds();
        }

        if ($this->item === null) {
            return [];
        }

        return [$this->item['id'], ...Inventory::descendantIdsOf($this->item['id'])];
    }

    protected function queuedResource(): string
    {
        return 'items';
    }

    protected function queuedRecordId(): ?int
    {
        return $this->item['id'] ?? null;
    }

    public function render(): View
    {
        return view('native.inventory-item-detail');
    }
}
