<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ChoosesInventoryParent;
use App\Concerns\SavesSunnyRecord;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyWrites;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;
use Sunny\ItemScanner\Events\IdentificationFailed;
use Sunny\ItemScanner\Events\ItemsIdentified;
use Sunny\ItemScanner\Facades\ItemScanner;
use Throwable;

/**
 * Photograph a shelf, drawer, or bin and let Apple's on-device model list
 * what's in it, then add the chosen finds to Sunny in one go.
 */
class ScanInventory extends NativeComponent
{
    use ChecksSunnySync;
    use ChoosesInventoryParent;
    use SavesSunnyRecord;

    /** Why the on-device model can't run here, or null when it can. */
    public ?string $unavailableReason = null;

    /**
     * Identify requests still waiting on the model, keyed by request id,
     * with the photo each one is looking at.
     *
     * @var array<string, string>
     */
    public array $pendingScans = [];

    /**
     * Photos the model couldn't read, as messages for the user.
     *
     * @var list<string>
     */
    public array $scanErrors = [];

    /**
     * Everything found so far, merged across photos by name. Each find keeps
     * the photo it was first seen in, which becomes the item's photo.
     *
     * @var list<array{id: int, name: string, photoPath: string, category: string, brand: string|null, model: string|null, quantity: int, existingMatch: string|null, selected: bool, error: string|null}>
     */
    public array $candidates = [];

    public int $nextCandidateId = 1;

    public string $error = '';

    public function mount(): void
    {
        $this->initializeTeam();

        $parent = $this->parentChoice((int) $this->data('parent'));
        if ($parent !== null) {
            $this->parentId = $parent['id'];
        }

        $this->unavailableReason = ItemScanner::availability()['reason'];
    }

    #[Computed]
    public function unavailableMessage(): ?string
    {
        return match ($this->unavailableReason) {
            null => null,
            'bridgeUnavailable' => 'Scanning uses Apple Intelligence, so it only works on iPhone for now.',
            'unsupportedOS' => 'Scanning needs iOS 27 or later. Update your iPhone to use it.',
            'deviceNotEligible', 'visionUnsupported' => 'This iPhone can’t run Apple Intelligence, which scanning needs.',
            'appleIntelligenceNotEnabled' => 'Turn on Apple Intelligence in Settings to scan items.',
            default => 'Apple Intelligence is still getting ready. Try again in a few minutes.',
        };
    }

    #[Computed]
    public function selectedCount(): int
    {
        return collect($this->candidates)->where('selected', true)->count();
    }

    public function takePhoto(): void
    {
        Camera::getPhoto()->start();
    }

    public function choosePhotos(): void
    {
        Camera::pickImages('image', multiple: true, max_items: 5)->start();
    }

    #[On(PhotoTaken::class)]
    public function photoTaken(string $path): void
    {
        $this->identify($path);
    }

    /**
     * @param  list<array{path?: string}>  $files
     */
    #[On(MediaSelected::class)]
    public function mediaSelected(bool $success, array $files = []): void
    {
        if (! $success) {
            return;
        }

        foreach ($files as $file) {
            if (($file['path'] ?? null) !== null) {
                $this->identify($file['path']);
            }
        }
    }

    /**
     * @param  list<array{name?: string, category?: string, brand?: string|null, model?: string|null, quantity?: int, existingMatch?: string|null}>  $items
     */
    #[On(ItemsIdentified::class)]
    public function itemsIdentified(string $id, array $items = []): void
    {
        $photoPath = $this->finishScan($id);

        if ($photoPath === null) {
            return;
        }

        foreach ($items as $item) {
            $this->mergeCandidate($item, $photoPath);
        }

        if ($items === []) {
            $this->scanErrors[] = 'No items found in that photo. Try getting closer.';
        }
    }

    #[On(IdentificationFailed::class)]
    public function identificationFailed(string $id, string $message): void
    {
        if ($this->finishScan($id) !== null) {
            $this->scanErrors[] = $message;
        }
    }

    public function toggleCandidate(int $id, bool $selected): void
    {
        $this->updateCandidate($id, ['selected' => $selected]);
    }

    public function renameCandidate(int $id, string $name): void
    {
        $this->updateCandidate($id, ['name' => $name]);
    }

    public function dismissScanErrors(): void
    {
        $this->scanErrors = [];
    }

    /**
     * Add the chosen finds to Sunny one at a time. The first failure stops
     * the run and stays in the list with its reason, so a retry only sends
     * what hasn't been saved. A find whose photo the system has since
     * cleared out is still added, just without the photo.
     */
    public function save(): void
    {
        if ($this->saving) {
            return;
        }

        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saving = true;
        $localCopyFailed = false;

        try {
            foreach ($this->candidates as $index => $candidate) {
                if (! $candidate['selected']) {
                    continue;
                }

                try {
                    $record = app(SunnyWrites::class)->save(
                        'items',
                        $this->teamId,
                        $this->candidatePayload($candidate),
                        photoPath: is_file($candidate['photoPath']) ? $candidate['photoPath'] : null,
                    );
                } catch (Throwable $exception) {
                    if ($this->isUnexpectedSaveFailure($exception)) {
                        report($exception);
                    }

                    $this->candidates[$index]['error'] = $this->saveFailureMessage($exception);
                    $this->error = 'Some items weren’t added. Fix the one marked below and try again.';

                    break;
                }

                try {
                    app(SunnyStore::class)->saveRecord('items', $record);
                } catch (Throwable $exception) {
                    report($exception);
                    $localCopyFailed = true;
                }

                unset($this->candidates[$index]);
            }
        } finally {
            $this->candidates = array_values($this->candidates);
            $this->saving = false;
        }

        if ($this->error !== '') {
            return;
        }

        if ($localCopyFailed) {
            $this->error = 'Added to Sunny, but some items aren’t on this phone yet. Sync to see them.';

            return;
        }

        $this->replace($this->parentId === null ? '/inventory' : '/inventory/'.$this->parentId);
    }

    public function render(): View
    {
        return view('native.scan-inventory');
    }

    protected function identify(string $path): void
    {
        if ($this->unavailableReason !== null) {
            return;
        }

        $id = ItemScanner::identify($path, $this->knownNames(), $this->placeDescription());

        $this->pendingScans[$id] = $path;
    }

    /**
     * Stop waiting on a scan, returning the photo it looked at, or null when
     * this screen didn't start it.
     */
    protected function finishScan(string $id): ?string
    {
        $photoPath = $this->pendingScans[$id] ?? null;

        unset($this->pendingScans[$id]);

        return $photoPath;
    }

    /**
     * The names already inside the chosen parent, so the model can flag
     * things that are already in the inventory.
     *
     * @return list<string>
     */
    protected function knownNames(): array
    {
        $items = collect(Inventory::all())->where('team_id', $this->teamId);

        if ($this->parentId !== null) {
            $items = $items->whereIn('id', Inventory::descendantIdsOf($this->parentId));
        }

        return $items->where('type', ItemType::Item)->pluck('name')->unique()->values()->all();
    }

    protected function placeDescription(): ?string
    {
        if ($this->selectedParent === null) {
            return null;
        }

        return $this->selectedParent['path'] === null
            ? $this->selectedParent['name']
            : $this->selectedParent['path'].' › '.$this->selectedParent['name'];
    }

    /**
     * Add a find to the list, or fold it into one with the same name.
     *
     * @param  array{name?: string, category?: string, brand?: string|null, model?: string|null, quantity?: int, existingMatch?: string|null}  $item
     */
    protected function mergeCandidate(array $item, string $photoPath): void
    {
        $name = Str::limit(trim((string) ($item['name'] ?? '')), 255, '');

        if ($name === '') {
            return;
        }

        $found = [
            'category' => $this->optionalString($item['category'] ?? null) ?? '',
            'brand' => $this->optionalString($item['brand'] ?? null),
            'model' => $this->optionalString($item['model'] ?? null),
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'existingMatch' => $this->optionalString($item['existingMatch'] ?? null),
        ];

        $index = collect($this->candidates)->search(fn (array $candidate): bool => Str::lower(trim($candidate['name'])) === Str::lower($name));

        if ($index === false) {
            $this->candidates[] = [
                'id' => $this->nextCandidateId++,
                'name' => $name,
                'photoPath' => $photoPath,
                ...$found,
                'selected' => $found['existingMatch'] === null,
                'error' => null,
            ];

            return;
        }

        $candidate = $this->candidates[$index];

        $this->candidates[$index] = [
            ...$candidate,
            'category' => $candidate['category'] !== '' ? $candidate['category'] : $found['category'],
            'brand' => $candidate['brand'] ?? $found['brand'],
            'model' => $candidate['model'] ?? $found['model'],
            'quantity' => max($candidate['quantity'], $found['quantity']),
            'existingMatch' => $candidate['existingMatch'] ?? $found['existingMatch'],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function updateCandidate(int $id, array $changes): void
    {
        foreach ($this->candidates as $index => $candidate) {
            if ($candidate['id'] === $id) {
                $this->candidates[$index] = [...$candidate, ...$changes, 'error' => null];
            }
        }
    }

    /**
     * @param  array{name: string, category: string, brand: string|null, model: string|null, quantity: int}  $candidate
     * @return array{name: string, type: string, parent_id: int|null, metadata: array<string, string>|null}
     */
    protected function candidatePayload(array $candidate): array
    {
        $metadata = array_filter([
            'brand' => $candidate['brand'],
            'model' => $candidate['model'],
            'category' => $candidate['category'] !== '' ? $candidate['category'] : null,
            'quantity' => $candidate['quantity'] > 1 ? (string) $candidate['quantity'] : null,
        ], fn (?string $value): bool => $value !== null);

        return [
            'name' => trim($candidate['name']),
            'type' => ItemType::Item->value,
            'parent_id' => $this->parentId,
            'metadata' => $metadata ?: null,
        ];
    }

    protected function validationError(): string
    {
        $selected = collect($this->candidates)->where('selected', true);

        if ($selected->isEmpty()) {
            return 'Choose at least one item to add.';
        }

        if ($selected->contains(fn (array $candidate): bool => trim($candidate['name']) === '')) {
            return 'Give every item a name.';
        }

        if ($selected->contains(fn (array $candidate): bool => mb_strlen(trim($candidate['name'])) > 255)) {
            return 'Item names are too long (255 characters max).';
        }

        if ($this->parentId !== null && $this->selectedParent === null) {
            return 'Choose a place in the same team.';
        }

        if ($this->teamId === null) {
            return 'Select a team on the dashboard. If none are listed, sync with Sunny first.';
        }

        if ($this->teamId !== app(SunnyTeam::class)->current()?->id) {
            return 'The active team changed. Reopen this screen from the dashboard before saving.';
        }

        return '';
    }

    protected function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
