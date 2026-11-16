<?php

arch()->preset()->php();

arch('native components follow the SuperNative conventions')
    ->expect('App\NativeComponents')
    ->toExtend('Native\Mobile\Edge\NativeComponent')
    ->toHaveMethod('render');

arch('native components report their errors to NativeWatch')
    ->expect('App\NativeComponents')
    ->toUseTrait('ZXN\NativeWatch\Mobile\Concerns\ReportsNativeUiErrors');
