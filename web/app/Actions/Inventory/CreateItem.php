<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

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
            (new AddItemPhoto)->handle($item, $photo);
        }

        return $item;
    }
}
