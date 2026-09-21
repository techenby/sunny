<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

class Home extends NativeComponent
{
    public function openLogin(): void
    {
        Browser::inApp('https://sunnyhome.app/login');
    }

    public function openRegister(): void
    {
        Browser::inApp('https://sunnyhome.app/register');
    }

    public function render(): View
    {
        return view('native.home');
    }
}
