<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\Item;
use App\Models\Onboarding;
use Illuminate\Support\Facades\DB;

class UndoHomeSetUp
{
    public function __construct(private readonly SunnyOutbox $outbox) {}

    public function handle(Onboarding $onboarding): void
    {
        DB::transaction(function () use ($onboarding): void {
            foreach ($onboarding->starters() as $starter) {
                $record = $onboarding->starterRecord($starter['resource'], $starter['id']);

                if ($record !== null && ! ($starter['resource'] === 'items' && Item::query()->forCurrentServer()->where('parent_id', $record->id)->exists())) {
                    $this->outbox->delete($starter['resource'], $record->team_id, $record->id);
                }
            }

            $onboarding->update(['set_up_at' => null, 'skipped_at' => $onboarding->skipped_at ?? now(), 'starters' => null]);
        });

        app(SunnySyncCoordinator::class)->dispatch();
    }
}
