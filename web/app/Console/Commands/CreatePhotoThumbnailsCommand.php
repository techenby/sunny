<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Create thumbnails for item and recipe photos that do not have one yet')]
#[Signature('photos:thumbnails')]
class CreatePhotoThumbnailsCommand extends Command
{
    public function handle(CreateThumbnail $createThumbnail): int
    {
        $created = 0;
        $failed = 0;

        foreach ([Item::class, Recipe::class] as $model) {
            $model::withTrashed()->whereNotNull('photo_path')->whereNull('thumb_path')->lazyById()
                ->each(function (Item|Recipe $record) use ($createThumbnail, &$created, &$failed): void {
                    $path = $createThumbnail->handle($record->photo_path);

                    if ($path === null) {
                        $failed++;

                        return;
                    }

                    $record->update(['thumb_path' => $path]);
                    $created++;
                });
        }

        $this->components->info(__(':count thumbnail(s) created.', ['count' => $created]));

        if ($failed > 0) {
            $this->components->warn(__(':count photo(s) could not be read.', ['count' => $failed]));
        }

        return self::SUCCESS;
    }
}
