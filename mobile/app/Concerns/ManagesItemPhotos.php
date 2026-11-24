<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Icons\Android;
use App\Icons\Ios;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Ui\Menu;
use Illuminate\Support\Str;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\Elements\TopBarAction;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;

trait ManagesItemPhotos
{
    /** @var list<array{key: string, id: int|null, path: string|null, src: string}> */
    public array $photos = [];

    /** @var list<int> */
    public array $removedPhotoIds = [];

    public bool $photosChanged = false;

    public function takePhoto(): void
    {
        Camera::getPhoto()->start();
    }

    public function choosePhoto(): void
    {
        Camera::pickImages('image', true, max(1, $this->remainingPhotos()))->start();
    }

    #[On(PhotoTaken::class)]
    public function photoTaken(string $path): void
    {
        $this->addPhoto($path);
    }

    /** @param list<array{path?: string}> $files */
    #[On(MediaSelected::class)]
    public function mediaSelected(bool $success, array $files): void
    {
        if (! $success) {
            return;
        }

        foreach ($files as $file) {
            if (isset($file['path'])) {
                $this->addPhoto($file['path']);
            }
        }
    }

    public function removePhoto(int $index): void
    {
        if (! isset($this->photos[$index])) {
            return;
        }

        if ($this->photos[$index]['id'] !== null) {
            $this->removedPhotoIds[] = $this->photos[$index]['id'];
        }

        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        $this->photosChanged = true;
    }

    public function makePrimary(int $index): void
    {
        $this->movePhoto($index, 0);
    }

    public function movePhotoUp(int $index): void
    {
        $this->movePhoto($index, $index - 1);
    }

    public function movePhotoDown(int $index): void
    {
        $this->movePhoto($index, $index + 1);
    }

    /**
     * @return list<list<TopBarAction>>
     */
    public function photoMenus(string $refPrefix): array
    {
        return array_map(fn (int $index): array => $this->photoMenu($index, $refPrefix), array_keys($this->photos));
    }

    /**
     * @return list<TopBarAction>
     */
    protected function photoMenu(int $index, string $refPrefix): array
    {
        $ref = $refPrefix.'-photo-'.$index;
        $label = 'photo '.($index + 1);

        return array_values(array_filter([
            $index > 0 ? Menu::item($ref.'-primary', 'Make main photo', Ios::Star, Android::Star, 'makePrimary('.$index.')', 'Make '.$label.' the main photo') : null,
            $index > 0 ? Menu::item($ref.'-up', 'Move up', Ios::ArrowUp, Android::ArrowUpward, 'movePhotoUp('.$index.')', 'Move '.$label.' up') : null,
            $index < count($this->photos) - 1 ? Menu::item($ref.'-down', 'Move down', Ios::ArrowDown, Android::ArrowDownward, 'movePhotoDown('.$index.')', 'Move '.$label.' down') : null,
            Menu::delete($ref.'-remove', 'Delete photo', 'removePhoto('.$index.')', 'Delete '.$label),
        ]));
    }

    public function remainingPhotos(): int
    {
        return ItemPhoto::MAX_PER_ITEM - count($this->photos);
    }

    protected function loadPhotos(int $itemId): void
    {
        $this->photos = ItemPhoto::query()->forCurrentServer()->where('item_id', $itemId)->orderBy('position')->orderBy('id')->get()
            ->map(fn (ItemPhoto $photo): array => ['key' => 'photo-'.$photo->id, 'id' => $photo->id, 'path' => null, 'src' => (string) $photo->thumbnail()])
            ->values()
            ->all();
        $this->removedPhotoIds = [];
        $this->photosChanged = false;
    }

    protected function queuePhotos(int $itemId, int $teamId): void
    {
        if (! $this->photosChanged) {
            return;
        }

        $outbox = app(SunnyOutbox::class);
        $existing = ItemPhoto::query()->where('item_id', $itemId)->orderBy('position')->orderBy('id')->pluck('id');

        foreach ($this->removedPhotoIds as $id) {
            if ($existing->contains($id)) {
                $outbox->delete('item_photos', $teamId, $id);
            }
        }

        $ids = [];
        $added = [];

        foreach ($this->photos as $photo) {
            if ($photo['id'] !== null) {
                $ids[] = $photo['id'];
            } elseif ($photo['path'] !== null) {
                $ids[] = $added[] = $outbox->queue('item_photos', $teamId, ['item_id' => $itemId], null, $photo['path']);
            }
        }

        foreach ($ids as $position => $id) {
            ItemPhoto::query()->whereKey($id)->update(['position' => $position]);
        }

        $natural = [...$existing->diff($this->removedPhotoIds)->values()->all(), ...$added];

        if ($ids !== $natural) {
            $outbox->queue('items', $teamId, ['photo_order' => $ids], $itemId);
        }

        $primary = ItemPhoto::query()->find($ids[0] ?? null);

        Item::query()->whereKey($itemId)->update([
            'photo_url' => $primary?->url,
            'thumb_url' => $primary?->thumb_url,
            'thumb_path' => $primary?->thumb_path,
        ]);
    }

    private function addPhoto(string $path): void
    {
        if ($this->remainingPhotos() <= 0) {
            $this->error = 'You can add up to '.ItemPhoto::MAX_PER_ITEM.' photos.';

            return;
        }

        $this->photos[] = ['key' => 'new-'.Str::random(8), 'id' => null, 'path' => $path, 'src' => $path];
        $this->photosChanged = true;
    }

    private function movePhoto(int $from, int $to): void
    {
        if (! isset($this->photos[$from]) || $to < 0 || $to >= count($this->photos) || $from === $to) {
            return;
        }

        $photo = array_splice($this->photos, $from, 1);
        array_splice($this->photos, $to, 0, $photo);
        $this->photosChanged = true;
    }
}
