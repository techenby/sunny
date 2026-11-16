<?php

namespace App\NativeComponents;

use Native\Mobile\Edge\NativeComponent;
use ZXN\NativeWatch\Mobile\Concerns\ReportsNativeUiErrors;

abstract class Component extends NativeComponent
{
    use ReportsNativeUiErrors;
}
