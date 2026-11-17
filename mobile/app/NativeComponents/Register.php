<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyConnector;
use Illuminate\View\View;
use Native\Mobile\Facades\Browser;

class Register extends Component
{
    public string $error = '';

    public function register(): void
    {
        $url = app(SunnyConnector::class)->siteUrl().'/register';
        $this->error = Browser::inApp($url) ? '' : 'Unable to open registration. Please try again.';
    }

    public function showLogin(): void
    {
        if ($this->data('from') === 'login') {
            $this->back();

            return;
        }

        $this->navigate('/login', ['from' => 'register']);
    }

    public function render(): View
    {
        return view('native.register');
    }
}
