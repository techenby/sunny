<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class UpdateItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Item $item, array $data, bool $removePhoto = false): Item
    {
        $photo = Arr::pull($data, 'photo');
        $photoOrder = Arr::pull($data, 'photo_order');

        $item->update($data);

        if ($photo instanceof UploadedFile || $removePhoto) {
            $primary = $item->primaryPhoto()->first();

            if ($primary) {
                (new RemoveItemPhoto)->handle($primary);
            }
        }

        if ($photo instanceof UploadedFile) {
            (new AddItemPhoto)->handle($item, $photo, position: 0);
        }

        if (is_array($photoOrder)) {
            (new OrderItemPhotos)->handle($item, array_map(intval(...), $photoOrder));
        }

        return $item->unsetRelation('primaryPhoto')->unsetRelation('photos');
    }
}
