<?php

namespace Tests\Fixtures;

use Illuminate\View\View;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
use Native\Mobile\Edge\NativeComponent;

class UiKitScreen extends NativeComponent
{
    public string $name = 'Camping gear';

    public int $saves = 0;

    public function save(): void
    {
        $this->saves++;
    }

    public function render(): View
    {
        return view('ui-kit-screen', [
            'menu' => [NavAction::make('rename')->label('Rename')->press('save')],
        ]);
    }
}
