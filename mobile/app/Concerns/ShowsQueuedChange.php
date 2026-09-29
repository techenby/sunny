<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

/**
 * Whether the record on screen has a change still waiting to reach Sunny,
 * and a way to throw that change away, after confirming, once Sunny has
 * refused it.
 */
trait ShowsQueuedChange
{
    /** @return array{error: string|null}|null */
    #[Computed]
    public function queuedChange(): ?array
    {
        $id = $this->queuedRecordId();

        return $id === null ? null : app(SunnyOutbox::class)->status($this->queuedResource(), $id);
    }

    public function confirmDiscardQueuedChange(): void
    {
        $id = $this->queuedRecordId();

        if ($id === null || $this->queuedChange === null) {
            return;
        }

        $noun = $this->queuedResource() === 'items' ? 'item' : 'recipe';

        Dialog::alert('Discard change?', $id < 0
            ? "This {$noun} never reached Sunny, so it will be deleted from this phone."
            : "Your edit will be replaced by the {$noun} on Sunny the next time this phone syncs.", [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Discard', 'style' => 'destructive'],
            ])->id('discard-queued-change')->show();
    }

    #[On(ButtonPressed::class)]
    public function onDiscardQueuedChangePressed(string $label, ?string $id = null): void
    {
        if ($id === 'discard-queued-change' && $label === 'Discard') {
            $this->discardQueuedChange();
        }
    }

    public function discardQueuedChange(): void
    {
        $id = $this->queuedRecordId();

        if ($id === null || $this->queuedChange === null) {
            return;
        }

        app(SunnyOutbox::class)->discard($this->queuedResource(), $id);
        app(SunnySyncCoordinator::class)->dispatch();

        if ($id < 0) {
            $this->back();

            return;
        }

        $this->refreshLocalSyncedData();
    }

    abstract protected function queuedResource(): string;

    abstract protected function queuedRecordId(): ?int;
}
