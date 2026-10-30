<?php

// Router for the orb's `web` service (see .amp/services.yaml). Amp portals terminate
// TLS and forward plain HTTP, so mark forwarded HTTPS requests as secure before handing
// off to Laravel's `artisan serve` router; otherwise Laravel builds http:// asset and
// Livewire URLs that the browser blocks as mixed content.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

return require getcwd() . '/../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
