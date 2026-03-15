<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class GenerateItemQrCode
{
    public function handle(Item $item): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd,
        );

        $url = route('inventory.index', ['parentId' => $item->id]);

        return new Writer($renderer)->writeString($url);
    }
}
