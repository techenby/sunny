<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyConnector;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

class Register extends NativeComponent
{
    public string $error = '';

    public function register(): void
    {
        $baseUrl = app(SunnyConnector::class)->resolveBaseUrl();
        $url = preg_replace('~/api$~', '', $baseUrl).'/register';
        $this->error = Browser::open($url) ? '' : 'Unable to open registration. Please try again.';
    }

    public function render(): View
    {
        return view('native.register');
    }
}
