<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ChoosesInventoryParent;
use App\Concerns\SavesSunnyRecord;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\ScanDraft;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;
use Native\Mobile\Facades\Dialog;
use Sunny\ItemScanner\Events\CaptureFailed;
use Sunny\ItemScanner\Events\IdentificationFailed;
use Sunny\ItemScanner\Events\ItemIdentified;
use Sunny\ItemScanner\Events\ItemRepeated;
use Sunny\ItemScanner\Events\PhotoCaptured;
use Sunny\ItemScanner\Facades\ItemScanner;
use Throwable;

/**
 * Photograph items one at a time and let Apple's on-device model name each
 * one in the background, then add them all to Sunny in one go.
 */
class ScanInventory extends NativeComponent
{
    use ChecksSunnySync;
    use ChoosesInventoryParent {
        selectParent as protected chooseParent;
        selectTopLevel as protected chooseTopLevel;
    }
    use SavesSunnyRecord;

    protected const CAPTURE_ID = 'scan';

    protected const RETAKE_PREFIX = 'retake-';

    /** Why the on-device model can't run here, or null when it can. */
    public ?string $unavailableReason = null;

    public string $batch = '';

    public string $category = '';

    /**
     * @var list<array{id: int, name: string, suggestedName: string, photoPath: string, category: string, quantity: int, extraCopies: int, scanId: string|null, scanError: string|null, error: string|null}>
     */
    public array $candidates = [];

    public int $nextCandidateId = 1;

    public ?int $lastCapturedCandidateId = null;

    public string $error = '';

    public function mount(): void
    {
        $this->initializeTeam();

        $this->unavailableReason = ItemScanner::availability()['reason'];

        $this->restoreDraft();

        $parent = $this->parentChoice((int) $this->data('parent'));
        if ($parent !== null) {
            $this->parentId = $parent['id'];
        }
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
    public function identifyingCount(): int
    {
        return collect($this->candidates)->whereNotNull('scanId')->count();
    }

    public function takePhotos(): void
    {
        $this->error = '';

        ItemScanner::capture(self::CAPTURE_ID);
    }

    public function retakePhoto(int $id): void
    {
        ItemScanner::capture(self::RETAKE_PREFIX.$id, single: true);
    }

    public function choosePhotos(): void
    {
        Camera::pickImages('image', multiple: true, max_items: 5)->start();
    }

    #[On(PhotoCaptured::class)]
    public function photoCaptured(string $id, string $path): void
    {
        if (str_starts_with($id, self::RETAKE_PREFIX)) {
            $this->replacePhoto((int) Str::after($id, self::RETAKE_PREFIX), $path);
        } elseif ($id === self::CAPTURE_ID) {
            $this->lastCapturedCandidateId = $this->nextCandidateId;
            $this->addCandidate($path);
        }
    }

    #[On(ItemRepeated::class)]
    public function itemRepeated(string $id): void
    {
        $candidate = collect($this->candidates)->firstWhere('id', $this->lastCapturedCandidateId);

        if ($id === self::CAPTURE_ID && $candidate !== null) {
            $this->updateCandidate($candidate['id'], ['extraCopies' => $candidate['extraCopies'] + 1]);
            $this->persistDraft();
        }
    }

    #[On(CaptureFailed::class)]
    public function captureFailed(string $id, string $message): void
    {
        $this->error = $message;
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
                $this->addCandidate($file['path']);
            }
        }
    }

    /**
     * @param  array{name?: string, category?: string, quantity?: int}  $item
     */
    #[On(ItemIdentified::class)]
    public function itemIdentified(string $id, array $item = []): void
    {
        $candidate = $this->candidateForScan($id);

        if ($candidate === null) {
            return;
        }

        $name = Str::limit(trim((string) ($item['name'] ?? '')), 255, '');

        $this->updateCandidate($candidate['id'], [
            'suggestedName' => $name,
            'category' => $this->optionalString($item['category'] ?? null) ?? '',
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'scanId' => null,
            'scanError' => $name === '' ? 'Couldn’t tell what that is. Type a name instead.' : null,
        ]);
        $this->persistDraft();
    }

    #[On(IdentificationFailed::class)]
    public function identificationFailed(string $id, string $message, ?string $detail = null): void
    {
        $candidate = $this->candidateForScan($id);

        if ($detail !== null) {
            Log::warning('Item identification failed', ['message' => $message, 'detail' => $detail]);
        }

        if ($candidate !== null) {
            $this->updateCandidate($candidate['id'], ['scanId' => null, 'scanError' => $message]);
            $this->persistDraft();
        }
    }

    public function renameCandidate(int $id, string $name): void
    {
        $this->updateCandidate($id, ['name' => $name]);
        $this->persistDraft();
    }

    public function useSuggestion(int $id): void
    {
        $candidate = collect($this->candidates)->firstWhere('id', $id);

        if ($candidate !== null && $candidate['suggestedName'] !== '') {
            $this->renameCandidate($id, $candidate['suggestedName']);
        }
    }

    public function removeCandidate(int $id): void
    {
        $removed = collect($this->candidates)->firstWhere('id', $id);

        $this->candidates = collect($this->candidates)
            ->reject(fn (array $candidate): bool => $candidate['id'] === $id)
            ->values()
            ->all();

        ScanDraft::forgetPhoto($removed['photoPath'] ?? null);
        $this->persistDraft();
    }

    public function updateBatch(string $batch): void
    {
        $this->batch = Str::limit(trim($batch), 255, '');
        $this->persistDraft();
    }

    public function updateCategory(string $category): void
    {
        $this->category = Str::limit(trim($category), 255, '');
        $this->persistDraft();
    }

    public function selectParent(int $id): void
    {
        $this->chooseParent($id);
        $this->persistDraft();
    }

    public function selectTopLevel(): void
    {
        $this->chooseTopLevel();
        $this->persistDraft();
    }

    public function confirmStartOver(): void
    {
        Dialog::alert('Start over?', 'The photos and names in this list will be cleared. Nothing has been added to your inventory yet.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Start over', 'style' => 'destructive'],
        ])->id('start-over')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'start-over' && $label === 'Start over') {
            $this->startOver();
        } elseif ($id === 'add-items' && $label === 'Add') {
            $this->save();
        }
    }

    public function confirmSave(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $count = count($this->candidates);
        $place = $this->placeDescription();

        Dialog::alert(
            trans_choice('Add :count item?|Add :count items?', $count),
            $place === null ? 'They’ll be added to the top level of your inventory.' : 'They’ll be added to '.$place.'.',
            [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Add', 'style' => 'default'],
            ],
        )->id('add-items')->show();
    }

    /**
     * Keep the photographed items on this phone and queue them for Sunny. The first
     * failure stops the run and stays in the list with its reason, so a retry
     * only adds what hasn't been kept. A find whose photo the system has since
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

        try {
            foreach (array_reverse($this->candidates, preserve_keys: true) as $index => $candidate) {
                try {
                    app(SunnyOutbox::class)->queue(
                        'items',
                        $this->teamId,
                        $this->candidatePayload($candidate),
                        photoPath: is_file($candidate['photoPath']) ? $candidate['photoPath'] : null,
                    );
                } catch (Throwable $exception) {
                    $this->candidates[$index]['error'] = $this->saveFailureMessage($exception);
                    $this->error = 'Some items weren’t added. Fix the one marked below and try again.';

                    break;
                }

                ScanDraft::forgetPhoto($candidate['photoPath']);
                unset($this->candidates[$index]);
            }
        } finally {
            $this->candidates = array_values($this->candidates);
            $this->saving = false;
            $this->persistDraft();
            app(SunnySyncCoordinator::class)->dispatch();
        }

        if ($this->error !== '') {
            return;
        }

        if ($this->parentId === ($this->parentChoice((int) $this->data('parent'))['id'] ?? null)) {
            $this->back();
        } else {
            $this->replace($this->parentId === null ? '/inventory' : '/inventory/'.$this->parentId);
        }
    }

    public function render(): View
    {
        return view('native.scan-inventory');
    }

    protected function addCandidate(string $photoPath): void
    {
        $photoPath = $this->keepPhoto($photoPath);

        array_unshift($this->candidates, [
            'id' => $this->nextCandidateId++,
            'name' => '',
            'suggestedName' => '',
            'photoPath' => $photoPath,
            'category' => '',
            'quantity' => 1,
            'extraCopies' => 0,
            'scanId' => $this->identify($photoPath),
            'scanError' => null,
            'error' => null,
        ]);
        $this->persistDraft();
    }

    protected function replacePhoto(int $id, string $photoPath): void
    {
        $previous = collect($this->candidates)->firstWhere('id', $id);

        if ($previous === null) {
            return;
        }

        $photoPath = $this->keepPhoto($photoPath);

        $this->updateCandidate($id, [
            'photoPath' => $photoPath,
            'suggestedName' => '',
            'scanId' => $this->identify($photoPath),
            'scanError' => null,
        ]);
        ScanDraft::forgetPhoto($previous['photoPath']);
        $this->persistDraft();
    }

    protected function identify(string $photoPath): ?string
    {
        if ($this->unavailableReason !== null) {
            return null;
        }

        $batchNames = collect($this->candidates)
            ->map(fn (array $candidate): string => $this->nameOf($candidate))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ItemScanner::identify($photoPath, $this->placeDescription(), $this->optionalString($this->batch), $batchNames);
    }

    protected function keepPhoto(string $path): string
    {
        if (! is_file($path)) {
            return $path;
        }

        File::ensureDirectoryExists(ScanDraft::photoDirectory());
        $kept = ScanDraft::photoDirectory().'/'.Str::uuid().'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        File::copy($path, $kept);

        return $kept;
    }

    protected function draft(): ?ScanDraft
    {
        if ($this->teamId === null) {
            return null;
        }

        return ScanDraft::query()->forCurrentServer()->where('team_id', $this->teamId)->first();
    }

    protected function restoreDraft(): void
    {
        $draft = $this->draft();

        if ($draft === null) {
            return;
        }

        $this->batch = $draft->batch ?? '';
        $this->category = $draft->category ?? '';
        $this->nextCandidateId = $draft->next_candidate_id;
        $this->candidates = collect($draft->candidates)
            ->map(fn (array $candidate): array => ['suggestedName' => '', 'extraCopies' => 0, ...Arr::except($candidate, ['nameEdited', 'brand', 'model'])])
            ->all();
        $this->parentId = $this->parentChoice((int) $draft->parent_id)['id'] ?? null;

        foreach ($this->candidates as $candidate) {
            if ($candidate['scanId'] !== null) {
                $this->updateCandidate($candidate['id'], ['scanId' => $this->identify($candidate['photoPath'])]);
            }
        }
    }

    protected function persistDraft(): void
    {
        if ($this->teamId === null) {
            return;
        }

        if ($this->candidates === []) {
            $this->draft()?->delete();

            return;
        }

        ScanDraft::query()->updateOrCreate(['server' => SunnyStore::server(), 'team_id' => $this->teamId], [
            'parent_id' => $this->parentId,
            'batch' => $this->optionalString($this->batch),
            'category' => $this->optionalString($this->category),
            'candidates' => $this->candidates,
            'next_candidate_id' => $this->nextCandidateId,
        ]);
    }

    protected function startOver(): void
    {
        $this->draft()?->discard();

        $this->candidates = [];
        $this->batch = '';
        $this->category = '';
        $this->error = '';
    }

    /**
     * @return array{id: int, name: string, suggestedName: string, scanId: string|null}|null
     */
    protected function candidateForScan(string $scanId): ?array
    {
        return collect($this->candidates)->firstWhere('scanId', $scanId);
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
     * @param  array{name: string, suggestedName: string, category: string, quantity: int, extraCopies: int}  $candidate
     * @return array{name: string, type: string, parent_id: int|null, metadata: array<string, string>|null}
     */
    protected function candidatePayload(array $candidate): array
    {
        $metadata = array_filter([
            'category' => $this->optionalString($this->category) ?? $this->optionalString($candidate['category']),
            'quantity' => $this->quantityOf($candidate) > 1 ? (string) $this->quantityOf($candidate) : null,
        ], fn (?string $value): bool => $value !== null);

        return [
            'name' => $this->nameOf($candidate),
            'type' => ItemType::Item->value,
            'parent_id' => $this->parentId,
            'metadata' => $metadata ?: null,
        ];
    }

    protected function validationError(): string
    {
        $candidates = collect($this->candidates);
        $unnamed = $candidates->filter(fn (array $candidate): bool => $this->nameOf($candidate) === '');

        if ($candidates->isEmpty()) {
            return 'Take a photo of at least one item.';
        }

        if ($unnamed->contains(fn (array $candidate): bool => $candidate['scanId'] !== null)) {
            return 'Some items are still being identified. Wait a moment or name them yourself.';
        }

        if ($unnamed->isNotEmpty()) {
            return 'Give every item a name.';
        }

        if ($candidates->contains(fn (array $candidate): bool => mb_strlen($this->nameOf($candidate)) > 255)) {
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

    /**
     * @param  array{name: string, suggestedName: string}  $candidate
     */
    protected function nameOf(array $candidate): string
    {
        return trim($candidate['name']) !== '' ? trim($candidate['name']) : $candidate['suggestedName'];
    }

    /**
     * @param  array{quantity: int, extraCopies: int}  $candidate
     */
    protected function quantityOf(array $candidate): int
    {
        return $candidate['quantity'] + $candidate['extraCopies'];
    }

    protected function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
