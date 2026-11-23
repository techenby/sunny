<?php

namespace App\NativeComponents;

use App\Layouts\HidesTabBar;
use Native\Mobile\Edge\NativeComponent;
use ZXN\NativeWatch\Mobile\Concerns\ReportsNativeUiErrors;

abstract class Component extends NativeComponent
{
    use ReportsNativeUiErrors;

    public function shouldHideTabBar(): bool
    {
        return $this instanceof HidesTabBar || parent::shouldHideTabBar();
    }
}
