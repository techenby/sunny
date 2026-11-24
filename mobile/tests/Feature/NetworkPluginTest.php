<?php

use App\Providers\NativeServiceProvider;
use Native\Mobile\Providers\NetworkServiceProvider;

it('compiles the network plugin into native builds so the phone can report its connection', function (): void {
    expect((new NativeServiceProvider(app()))->plugins())->toContain(NetworkServiceProvider::class);
});
