<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use Illuminate\View\View;
use Native\Mobile\Edge\Layouts\Builders\NavAction;

class UiKitGallery extends Component
{
    public string $lastTapped = '';

    public bool $toggled = true;

    public string $timeOfDay = 'Morning';

    public string $repeats = 'Daily';

    public function tapped(string $example): void
    {
        $this->lastTapped = $example;
    }

    public function setToggled(bool $toggled): void
    {
        $this->toggled = $toggled;
        $this->lastTapped = 'settings-row toggle';
    }

    public function tappedMenu(): void
    {
        $this->lastTapped = 'menu';
    }

    public function render(): View
    {
        return view('native.ui-kit-gallery', [
            'menu' => [
                NavAction::make('ui-kit-menu-first')->label('First')->press('tappedMenu'),
                NavAction::make('ui-kit-menu-second')->label('Second')->press('tappedMenu'),
            ],
            'item' => [
                'id' => 0,
                'name' => 'Camping gear',
                'type' => ItemType::Bin,
                'photo_url' => null,
                'thumb_path' => null,
                'children_count' => 3,
            ],
        ]);
    }
}
