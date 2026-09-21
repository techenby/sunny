<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class Login extends NativeComponent
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function render(): View
    {
        return view('native.login');
    }
}
