<?php

namespace App\Http\Integrations\Sunny;

use App\Http\Integrations\Sunny\Requests\DeleteCalendarFeedRequest;
use App\Http\Integrations\Sunny\Requests\GetCalendarEventsRequest;
use App\Http\Integrations\Sunny\Requests\GetCalendarFeedsRequest;
use App\Http\Integrations\Sunny\Requests\SaveCalendarFeedRequest;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\SaloonException;
use UnexpectedValueException;

class SunnyCalendar
{
    public const COLORS = [
        '#2563eb' => 'Blue',
        '#16a34a' => 'Green',
        '#dc2626' => 'Red',
        '#9333ea' => 'Purple',
        '#ea580c' => 'Orange',
        '#0891b2' => 'Cyan',
        '#ca8a04' => 'Gold',
        '#4f46e5' => 'Indigo',
    ];

    public function __construct(private readonly SunnyAuth $auth) {}

    /**
     * @return 'ok'|'offline'|'unavailable'
     */
    public function refresh(int $teamId, string $from, int $days, #[\SensitiveParameter] ?string $token = null, bool $withFeeds = true): string
    {
        $team = Team::query()->where('server', SunnyStore::server())->find($teamId);

        if ($team === null || ! $team->slug) {
            return 'unavailable';
        }

        try {
            $connector = $this->auth->authenticatedConnector($token);
            $feeds = $withFeeds ? $connector->send(new GetCalendarFeedsRequest($team->slug))->json() : null;
            $events = $connector->send(new GetCalendarEventsRequest($team->slug, $from, $days))->json();
            throw_unless(is_array($events) && ($feeds === null || is_array($feeds)), UnexpectedValueException::class, 'Invalid calendar response.');

            if ($feeds !== null) {
                Validator::make($feeds, self::feedRules('data'))->validate();
            }

            Validator::make($events, [
                'data' => ['present', 'array', 'list'],
                'data.*.feed_id' => ['required', 'integer'],
                'data.*.feed_name' => ['required', 'string'],
                'data.*.feed_color' => ['required', 'string'],
                'data.*.title' => ['required', 'string'],
                'data.*.location' => ['nullable', 'string'],
                'data.*.date' => ['required', 'date_format:Y-m-d'],
                'data.*.all_day' => ['required', 'boolean'],
                'data.*.starts_at' => ['required', 'date'],
                'data.*.ends_at' => ['nullable', 'date'],
                'data.*.response_status' => ['nullable', 'string'],
                'warnings' => ['present', 'array', 'list'],
                'warnings.*.feed_id' => ['required', 'integer'],
                'warnings.*.feed_name' => ['required', 'string'],
                'warnings.*.message' => ['nullable', 'string'],
            ])->validate();
        } catch (FatalRequestException) {
            return 'offline';
        } catch (SaloonException|AuthenticationException|JsonException|ValidationException|RuntimeException) {
            return 'unavailable';
        }

        if ($feeds !== null) {
            $this->store($team->id, 'feeds', ['feeds' => $feeds['data']]);
        }

        $this->store($team->id, self::eventsKey($from, $days), ['events' => $events['data'], 'warnings' => $events['warnings']]);
        $this->pruneEvents($team);

        return 'ok';
    }

    /**
     * @return array{events: list<array<string, mixed>>, warnings: list<array{feed_id: int, feed_name: string, message: string|null}>, fetched_at: CarbonImmutable}|null
     */
    public function events(int $teamId, string $from, int $days): ?array
    {
        return $this->snapshot($teamId, self::eventsKey($from, $days));
    }

    /**
     * @return array{feeds: list<array<string, mixed>>, fetched_at: CarbonImmutable}|null
     */
    public function feeds(int $teamId): ?array
    {
        return $this->snapshot($teamId, 'feeds');
    }

    /**
     * @return 'ok'|'offline'|'unavailable'
     */
    public function refreshFeeds(int $teamId, #[\SensitiveParameter] ?string $token = null): string
    {
        $team = Team::query()->where('server', SunnyStore::server())->find($teamId);

        if ($team === null || ! $team->slug) {
            return 'unavailable';
        }

        try {
            $feeds = $this->auth->authenticatedConnector($token)->send(new GetCalendarFeedsRequest($team->slug))->json();
            throw_unless(is_array($feeds), UnexpectedValueException::class, 'Invalid calendar response.');
            Validator::make($feeds, self::feedRules('data'))->validate();
        } catch (FatalRequestException) {
            return 'offline';
        } catch (SaloonException|AuthenticationException|JsonException|ValidationException|RuntimeException) {
            return 'unavailable';
        }

        $this->store($team->id, 'feeds', ['feeds' => $feeds['data']]);

        return 'ok';
    }

    /**
     * @param  array{name?: string, url?: string, color?: string, client_uuid?: string}  $payload
     * @return array<string, mixed>
     */
    public function saveFeed(Team $team, array $payload, ?int $id = null): array
    {
        throw_unless($team->slug, UnexpectedValueException::class, 'Sync with Sunny first.');

        $feed = $this->auth->authenticatedConnector()->send(new SaveCalendarFeedRequest($team->slug, $payload, $id))->json('data');
        throw_unless(is_array($feed), UnexpectedValueException::class, 'Invalid calendar response.');
        Validator::make(['data' => [$feed]], self::feedRules('data'))->validate();

        $this->changeFeeds($team->id, fn (array $feeds): array => [
            ...array_filter($feeds, fn (array $existing): bool => $existing['id'] !== $feed['id']),
            $feed,
        ]);

        return $feed;
    }

    public function deleteFeed(Team $team, int $id): void
    {
        throw_unless($team->slug, UnexpectedValueException::class, 'Sync with Sunny first.');

        try {
            $this->auth->authenticatedConnector()->send(new DeleteCalendarFeedRequest($team->slug, $id));
        } catch (RequestException $exception) {
            throw_unless($exception->getResponse()->status() === 404, $exception);
        }

        $this->changeFeeds($team->id, fn (array $feeds): array => array_filter($feeds, fn (array $existing): bool => $existing['id'] !== $id));
    }

    public static function isStale(?array $snapshot): bool
    {
        return $snapshot === null || $snapshot['fetched_at']->lessThan(now()->subMinutes(5));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function feedRules(string $key): array
    {
        return [
            $key => ['present', 'array', 'list'],
            $key.'.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            $key.'.*.name' => ['required', 'string'],
            $key.'.*.url' => ['required', 'string'],
            $key.'.*.color' => ['required', 'string'],
            $key.'.*.status' => ['required', 'string'],
            $key.'.*.last_fetched_at' => ['nullable', 'date'],
            $key.'.*.last_failed_at' => ['nullable', 'date'],
            $key.'.*.last_error' => ['nullable', 'string'],
        ];
    }

    private static function eventsKey(string $from, int $days): string
    {
        return 'events:'.$from.':'.$days;
    }

    private function changeFeeds(int $teamId, Closure $change): void
    {
        $feeds = $this->feeds($teamId)['feeds'] ?? [];

        $this->store($teamId, 'feeds', ['feeds' => collect($change($feeds))->sortBy(fn (array $feed): string => mb_strtolower($feed['name']))->values()->all()]);
        DB::table('calendar_snapshots')->where('server', SunnyStore::server())->where('team_id', $teamId)->where('range', 'like', 'events:%')->delete();
    }

    private function store(int $teamId, string $range, array $payload): void
    {
        DB::table('calendar_snapshots')->updateOrInsert(
            ['server' => SunnyStore::server(), 'team_id' => $teamId, 'range' => $range],
            ['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'fetched_at' => now()],
        );
    }

    private function snapshot(int $teamId, string $range): ?array
    {
        $row = DB::table('calendar_snapshots')->where('server', SunnyStore::server())->where('team_id', $teamId)->where('range', $range)->first();

        if ($row === null) {
            return null;
        }

        $payload = json_decode($row->payload, true);

        return is_array($payload) ? [...$payload, 'fetched_at' => CarbonImmutable::parse($row->fetched_at)] : null;
    }

    private function pruneEvents(Team $team): void
    {
        $today = $team->today();

        DB::table('calendar_snapshots')
            ->where('server', SunnyStore::server())
            ->where('team_id', $team->id)
            ->where('range', 'like', 'events:%')
            ->pluck('range')
            ->filter(function (string $range) use ($today): bool {
                [, $from, $days] = explode(':', $range);

                return CarbonImmutable::parse($from, $today->getTimezone())->addDays((int) $days)->lessThanOrEqualTo($today->subDays(7));
            })
            ->each(fn (string $range) => DB::table('calendar_snapshots')->where('server', SunnyStore::server())->where('team_id', $team->id)->where('range', $range)->delete());
    }
}
