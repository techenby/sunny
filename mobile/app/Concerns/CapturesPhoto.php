<?php

namespace App\Concerns;

use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Facades\Camera;

/**
 * Photo capture for the create screens — the mobile stand-in for the web
 * app's file-upload dropzone, offering the camera or the photo library.
 *
 * The native camera events are screen-scoped, so this has to live on the
 * screen component rather than in a nested child component.
 */
trait CapturesPhoto
{
    /** On-device path to the captured or picked photo, once there is one. */
    public ?string $photoPath = null;

    public function takePhoto(): void
    {
        Camera::getPhoto()->start();
    }

    public function choosePhoto(): void
    {
        Camera::pickImages('image')->start();
    }

    public function removePhoto(): void
    {
        $this->photoPath = null;
    }

    #[On(PhotoTaken::class)]
    public function photoTaken(string $path): void
    {
        $this->photoPath = $path;
    }

    /**
     * The gallery picker hands back one entry per selection, each shaped
     * `{path, mimeType, extension, type}` on both platforms. Single-select is
     * configured in choosePhoto(), so only the first entry is ever used.
     *
     * @param  list<array{path?: string}>  $files
     */
    #[On(MediaSelected::class)]
    public function mediaSelected(bool $success, array $files): void
    {
        if (! $success) {
            return;
        }

        $path = $files[0]['path'] ?? null;

        if ($path !== null) {
            $this->photoPath = $path;
        }
    }
}
