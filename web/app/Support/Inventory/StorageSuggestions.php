<?php

declare(strict_types=1);

namespace App\Support\Inventory;

use Illuminate\Support\Str;

class StorageSuggestions
{
    private const array PLACES = [
        'garage' => [['Camping box', 'Tool chest', 'Holiday decorations', 'Paint shelf'], 'Tent'],
        'kitchen pantry' => [['Top shelf', 'Baking box', 'Snack basket', 'Spice rack'], 'Flour'],
        'hall closet' => [['Top shelf', 'Shoe rack', 'Gift wrap box', 'Cleaning caddy'], 'Umbrella'],
        'basement' => [['Storage shelves', 'Holiday decorations', 'Keepsake box', 'Tool bench'], 'Space heater'],
        'attic' => [['Holiday decorations', 'Keepsake box', 'Winter clothes box', 'Baby clothes box'], 'Ornaments'],
        'laundry room' => [['Cleaning shelf', 'Linen shelf', 'Sewing box', 'Detergent cabinet'], 'Stain remover'],
        'shed' => [['Garden tools rack', 'Seed box', 'Paint shelf', 'Tool chest'], 'Hedge trimmer'],
        "kids' rooms" => [['Toy box', 'Bookshelf', 'Art supplies box', 'Dresser'], 'Crayons'],
        'office' => [['Desk drawer', 'Filing cabinet', 'Cable box', 'Bookshelf'], 'Stapler'],
    ];

    private const array FALLBACK = [['Top shelf', 'Storage box', 'Drawer', 'Cabinet'], 'Flashlight'];

    /** @return list<string> */
    public static function storageFor(string $place): array
    {
        return array_map(__(...), self::match($place)[0]);
    }

    public static function exampleThingFor(string $place): string
    {
        return __(self::match($place)[1]);
    }

    /** @return array{0: list<string>, 1: string} */
    private static function match(string $place): array
    {
        return self::PLACES[Str::lower(trim($place))] ?? self::FALLBACK;
    }
}
