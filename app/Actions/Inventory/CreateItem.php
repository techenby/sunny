<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): Item
    {
        $photo = Arr::pull($data, 'photo');

        $item = $team->items()->create($data);

        if ($photo instanceof UploadedFile) {
            $filename = Str::slug($item->name) . '-' . $item->id . '.' . $photo->getClientOriginalExtension();

            $path = $photo->storeAs("teams/{$team->id}/items", $filename);

            $item->update(['photo_path' => $path]);
        }

        return $item;
    }
}
