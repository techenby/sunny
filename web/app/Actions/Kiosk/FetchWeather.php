<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Http\Integrations\OpenWeather\OpenWeatherConnector;
use App\Http\Integrations\OpenWeather\Requests\OneCall;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Saloon\Exceptions\Request\RequestException;

class FetchWeather
{
    /**
     * @return array{
     *     location: string|null,
     *     temp: float,
     *     high: float,
     *     low: float,
     *     description: string|null,
     *     icon: string|null
     * }|null
     */
    public function handle(Team $team): ?array
    {
        if (! ($team->address['lat'] ?? null) || ! ($team->address['long'] ?? null)) {
            return null;
        }

        try {
            $weather = Cache::remember(
                "weather:{$team->id}",
                now()->addMinutes(30),
                fn () => (new OpenWeatherConnector)->send(
                    new OneCall((float) $team->address['lat'], (float) $team->address['long'], 'minutely,hourly,alerts')
                )->json(),
            );
        } catch (RequestException) {
            return null;
        }

        if (! $weather) {
            return null;
        }

        return [
            'location' => $team->address['city'] ?? null,
            'temp' => round($weather['current']['temp']),
            'high' => round($weather['daily'][0]['temp']['max']),
            'low' => round($weather['daily'][0]['temp']['min']),
            'description' => $weather['current']['weather'][0]['description'] ?? null,
            'icon' => $weather['current']['weather'][0]['icon'] ?? null,
        ];
    }
}
