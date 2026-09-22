<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Dashboard extends NativeComponent
{
    public function logOut(): void
    {
        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.dashboard');
    }
}
