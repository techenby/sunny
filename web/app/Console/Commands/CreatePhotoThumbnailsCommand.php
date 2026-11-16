<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Photos\CreateThumbnail;
use App\Models\ItemPhoto;
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

        $create = function (ItemPhoto|Recipe $record, string $photoPath) use ($createThumbnail, &$created, &$failed): void {
            $path = $createThumbnail->handle($photoPath);

            if ($path === null) {
                $failed++;

                return;
            }

            $record->update(['thumb_path' => $path]);
            $created++;
        };

        ItemPhoto::query()->whereNull('thumb_path')->lazyById()
            ->each(fn (ItemPhoto $photo) => $create($photo, $photo->path));

        Recipe::withTrashed()->whereNotNull('photo_path')->whereNull('thumb_path')->lazyById()
            ->each(fn (Recipe $recipe) => $create($recipe, $recipe->photo_path));

        $this->components->info(__(':count thumbnail(s) created.', ['count' => $created]));

        if ($failed > 0) {
            $this->components->warn(__(':count photo(s) could not be read.', ['count' => $failed]));
        }

        return self::SUCCESS;
    }
}
