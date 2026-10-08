<?php

namespace Tests\Fixtures;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class UiKitScreen extends NativeComponent
{
    public string $name = 'Camping gear';

    public function render(): View
    {
        return view('ui-kit-screen');
    }
}
