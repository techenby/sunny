<?php

return [
    /*
     * The dashboard supports these themes:
     *
     * - light: always use light mode
     * - dark: always use dark mode
     * - device: follow the OS preference for determining light or dark mode
     * - auto: use light mode when the sun is up, dark mode when the sun is down
     */
    'theme' => 'device',

    /*
     * When the dashboard uses the `auto` theme, these coordinates will be used
     * to determine whether the sun is up or down.
     */
    'auto_theme_location' => [
        'lat' => 51.260197,
        'lng' => 4.402771,
    ],

    /*
     * These stylesheets will be loaded when the dashboard is displayed.
     */
    'stylesheets' => [
        'inter' => 'https://rsms.me/inter/inter.css',
    ],

'token' => env('LOG_POSE_TOKEN'),

    'tiles' => [
        'calendar' => [
            'andy' => [
                env('CAL_ANDY_PERSONAL'),
                env('CAL_ANDY_WORK'),
            ],
            'family' => [
                env('CAL_FAMILY'),
            ],
            'ashar' => [
                env('CAL_ASHAR'),
            ],
        ],
        'weather' => [
            'lat' => env('WEATHER_LAT'),
            'lon' => env('WEATHER_LON'),
        ],
    ],
];
