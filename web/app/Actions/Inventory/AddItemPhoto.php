<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class AddItemPhoto
{
    public function handle(Item $item, UploadedFile $photo, ?int $position = null, ?string $clientUuid = null): ItemPhoto
    {
        $filename = Str::slug($item->name) . '-' . $item->id . '-' . Str::lower(Str::random(8)) . '.' . $photo->getClientOriginalExtension();

        $path = $photo->storeAs("teams/{$item->team_id}/items", $filename);

        throw_if($path === false, RuntimeException::class, 'The photo could not be saved.');

        $created = $item->photos()->create([
            'path' => $path,
            'thumb_path' => (new CreateThumbnail)->handle($path),
            'position' => $item->photos()->count(),
            'client_uuid' => $clientUuid,
        ]);

        if ($position !== null) {
            (new MoveItemPhoto)->handle($created, $position);
        }

        return $created->refresh();
    }
}
