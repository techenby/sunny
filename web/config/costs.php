<?php

return [

    'sponsor_url' => 'https://github.com/sponsors/techenby',

    'cloud' => [
        'token' => env('LARAVEL_CLOUD_TOKEN'),
        'path' => resource_path('data/cloud-costs.json'),
        'applications' => [
            'app-a2d4f582-159c-4277-a145-af211fc3b97e',
            'app-a128ecca-a71c-4c66-a78c-dbeb5c2b844e',
        ],
        'resources' => ['sunny', 'sunny_test'],
    ],

    'services' => [
        'Domain (sunnyhome.app)' => null,
        'Nightwatch monitoring' => null,
        'Bento email' => null,
        'Apple Developer Program' => null,
    ],

];
