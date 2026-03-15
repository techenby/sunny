<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Item $item, array $data, bool $removePhoto = false): Item
    {
        $photo = Arr::pull($data, 'photo');

        $item->update($data);

        if ($removePhoto && ! $photo instanceof UploadedFile) {
            if ($item->photo_path) {
                Storage::delete($item->photo_path);
            }

            $item->update(['photo_path' => null]);
        } elseif ($photo instanceof UploadedFile) {
            if ($item->photo_path) {
                Storage::delete($item->photo_path);
            }

            $filename = Str::slug($item->name) . '-' . $item->id . '.' . $photo->getClientOriginalExtension();

            $path = $photo->storeAs("teams/{$item->team_id}/items", $filename);

            $item->update(['photo_path' => $path]);
        }

        return $item;
    }
}
