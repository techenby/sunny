<?php

namespace App\NativeComponents;

use App\Enums\ItemType;
use App\Icons\Android;
use App\Icons\Ios;
use App\Ui\Menu;
use Illuminate\View\View;

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
                Menu::item('ui-kit-menu-edit', 'Edit', Ios::Pencil, Android::Edit, 'tappedMenu'),
                Menu::item('ui-kit-menu-share', 'Share', Ios::SquareAndArrowUp, Android::Share, 'tappedMenu'),
                Menu::delete('ui-kit-menu-delete', 'Delete', 'tappedMenu'),
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
