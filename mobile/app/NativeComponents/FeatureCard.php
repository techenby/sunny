<?php

namespace App\NativeComponents;

use App\Icons\Android;
use App\Icons\Ios;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class FeatureCard extends NativeComponent
{
    public string $title = '';

    public string $description = '';

    public ?Ios $iosIcon = null;

    public ?Android $androidIcon = null;

    public bool $navigable = false;

    public function render(): View
    {
        return view('native.feature-card');
    }
}
