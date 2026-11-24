<?php

use App\Http\Integrations\Sunny\Requests\DeleteCalendarFeedRequest;
use App\Http\Integrations\Sunny\Requests\GetCalendarEventsRequest;
use App\Http\Integrations\Sunny\Requests\GetCalendarFeedsRequest;
use App\Http\Integrations\Sunny\Requests\GetRoutineOccurrencesRequest;
use App\Http\Integrations\Sunny\Requests\SaveCalendarFeedRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    $this->travelTo('2026-11-17 07:42:00');
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

function calendarFeed(int $id, string $name, string $color, array $overrides = []): array
{
    return [
        'id' => $id,
        'team_id' => 1,
        'name' => $name,
        'url' => 'https://calendar.example.com/'.strtolower($name).'.ics',
        'color' => $color,
        'status' => 'ok',
        'last_fetched_at' => '2026-11-17T07:30:00.000000Z',
        'last_failed_at' => null,
        'last_error' => null,
        'client_uuid' => null,
        'created_at' => '2026-11-01T00:00:00.000000Z',
        'updated_at' => '2026-11-17T07:30:00.000000Z',
        ...$overrides,
    ];
}

function failingWorkFeed(): array
{
    return calendarFeed(2, 'Work', '#16a34a', [
        'status' => 'failing',
        'last_failed_at' => '2026-11-17T05:42:00.000000Z',
        'last_error' => 'The calendar server responded with HTTP 401.',
    ]);
}

function calendarFeedsResponse(?array $feeds = null): MockResponse
{
    return MockResponse::make(['data' => $feeds ?? [
        calendarFeed(1, 'Family', '#2563eb'),
        calendarFeed(3, 'School', '#ea580c'),
        calendarFeed(2, 'Work', '#16a34a'),
    ]]);
}

function calendarEvent(string $title, string $startsAt, ?string $endsAt = null, array $overrides = []): array
{
    return [
        'feed_id' => 1,
        'feed_name' => 'Family',
        'feed_color' => '#2563eb',
        'title' => $title,
        'location' => null,
        'date' => substr($startsAt, 0, 10),
        'all_day' => false,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'response_status' => null,
        ...$overrides,
    ];
}

function calendarEventsResponse(array $events, array $warnings = []): MockResponse
{
    return MockResponse::make(['data' => $events, 'from' => '2026-11-17', 'days' => 2, 'timezone' => 'UTC', 'warnings' => $warnings]);
}

function workWarning(): array
{
    return ['feed_id' => 2, 'feed_name' => 'Work', 'message' => 'The calendar server responded with HTTP 401.'];
}

function sampleEvents(): array
{
    return [
        calendarEvent('Picture day', '2026-11-17T00:00:00+00:00', '2026-11-18T00:00:00+00:00', ['all_day' => true, 'feed_id' => 3, 'feed_name' => 'School', 'feed_color' => '#ea580c', 'location' => 'Lincoln Elementary']),
        calendarEvent('Design sync', '2026-11-17T09:30:00+00:00', '2026-11-17T10:00:00+00:00', ['feed_id' => 2, 'feed_name' => 'Work', 'feed_color' => '#16a34a', 'location' => 'Zoom', 'response_status' => 'DECLINED']),
        calendarEvent('Maya’s swim lesson', '2026-11-17T15:45:00+00:00', '2026-11-17T16:30:00+00:00', ['location' => 'Westside Y pool']),
    ];
}

function calendarOffline(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $request) => new FatalRequestException(new RuntimeException('Could not resolve host'), $request));
}

function seedCalendar(array $events, array $warnings = [], string $from = '2026-11-17', int $days = 2, ?array $feeds = null): void
{
    Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse($feeds), GetCalendarEventsRequest::class => calendarEventsResponse($events, $warnings)]);
    expect(app(SunnyCalendar::class)->refresh(1, $from, $days, 'saved-token'))->toBe('ok');
}

describe('Summary', function (): void {
    it('shows today’s and tomorrow’s events under Coming up', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse(sampleEvents())]);

        Native::visit('/dashboard')
            ->assertSee('Coming up')
            ->assertSee('Today')
            ->assertSee('Tue, Nov 17')
            ->assertSee('All day')
            ->assertSee('Picture day')
            ->assertSee('Lincoln Elementary')
            ->assertSee('9:30')
            ->assertSee('10:00 am')
            ->assertSee('Declined')
            ->assertSee('3:45')
            ->assertSee('4:30 pm')
            ->assertSee('Tomorrow')
            ->assertSee('Wed, Nov 18')
            ->assertSee('Nothing scheduled')
            ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'coming-up-0-1-title'
                && ($node['props']['line_through'] ?? null) === 1)
            ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'coming-up-1-empty')
            ->assertAccessible()
            ->tap('open-calendar')
            ->assertNavigatedTo('/calendar');

        Saloon::assertSent(fn (GetCalendarEventsRequest $request): bool => $request->resolveEndpoint() === '/teams/family/calendar-events'
            && $request->query()->all() === ['from' => '2026-11-17', 'days' => 2]);
    });

    it('says when there is nothing coming up', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse([])]);

        Native::visit('/dashboard')
            ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'coming-up-0-empty')
            ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'coming-up-1-empty');
    });

    it('points a team without calendars to the calendar screen', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse([]), GetCalendarEventsRequest::class => calendarEventsResponse([])]);

        Native::visit('/dashboard')
            ->assertSee('No calendars yet. Connect one to see what’s coming up.')
            ->assertDontSee('Nothing scheduled')
            ->tap('coming-up-no-calendars')
            ->assertNavigatedTo('/calendar');
    });

    it('names a failing calendar and links to fixing it', function (): void {
        Saloon::fake([
            GetCalendarFeedsRequest::class => calendarFeedsResponse([calendarFeed(1, 'Family', '#2563eb'), failingWorkFeed()]),
            GetCalendarEventsRequest::class => calendarEventsResponse([sampleEvents()[2]], [workWarning()]),
        ]);

        Native::visit('/dashboard')
            ->assertSee('Work isn’t showing')
            ->assertSee('Maya’s swim lesson')
            ->tap('coming-up-warning-2')
            ->assertNavigatedTo('/calendar/feeds');
    });

    it('shows the last events it fetched while offline', function (): void {
        seedCalendar(sampleEvents());
        $this->travel(2)->hours();
        Saloon::fake([GetCalendarFeedsRequest::class => calendarOffline(), GetCalendarEventsRequest::class => calendarOffline()]);

        Native::visit('/dashboard')
            ->assertSee('Picture day')
            ->assertSee('Maya’s swim lesson')
            ->assertSee('You’re offline. Last updated this morning at 7:42.')
            ->assertSet('calendarStatus', 'offline');
    });

    it('says events need a connection when it has never fetched them', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarOffline(), GetCalendarEventsRequest::class => calendarOffline()]);

        Native::visit('/dashboard')
            ->assertSee('You’re offline. Events show up here once the server can be reached.')
            ->assertDontSee('Nothing scheduled');
    });

    it('waits five minutes before fetching events again, unless pulled down', function (): void {
        Saloon::fake([
            GetCalendarFeedsRequest::class => calendarFeedsResponse(),
            GetCalendarEventsRequest::class => calendarEventsResponse(sampleEvents()),
            SyncRequest::class => MockResponse::make([], 503),
        ]);

        $screen = Native::visit('/dashboard');
        Native::visit('/dashboard');
        Saloon::assertSentCount(2);

        $screen->call('sync');
        Saloon::assertSent(GetCalendarEventsRequest::class);
        expect(collect(Saloon::mockClient()->getRecordedResponses())->filter(fn ($response): bool => $response->getRequest() instanceof GetCalendarEventsRequest))->toHaveCount(2);
    });
});

describe('Calendar', function (): void {
    it('lists six weeks of events by day, starting today', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse([
            calendarEvent('Trip', '2026-11-15T00:00:00+00:00', '2026-11-19T00:00:00+00:00', ['all_day' => true, 'date' => '2026-11-15']),
            ...sampleEvents(),
            calendarEvent('Parent–teacher conference', '2026-11-20T12:15:00+00:00', '2026-11-20T13:00:00+00:00', ['location' => 'Room 12']),
            calendarEvent('New Year’s Eve', '2026-12-28T23:30:00+00:00', '2026-12-29T00:30:00+00:00'),
        ])]);

        Native::visit('/calendar')
            ->assertNavTitle('Calendar')
            ->assertDontSee('next 6 weeks')
            ->assertSee('Tuesday, Nov 17')
            ->assertSee('Trip')
            ->assertSee('Friday')
            ->assertSee('Nov 20')
            ->assertSee('12:15')
            ->assertSee('1:00 pm')
            ->assertSee('Monday')
            ->assertSee('Dec 28')
            ->assertSee('11:30 pm')
            ->assertSee('12:30 am')
            ->assertDontSee('Tomorrow')
            ->assertMissingElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-day-2026-11-15')
            ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-day-2026-11-17')
            ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-day-2026-11-20')
            ->assertAccessible();

        Saloon::assertSent(fn (GetCalendarEventsRequest $request): bool => $request->query()->all() === ['from' => '2026-11-17', 'days' => 42]);
    });

    it('shows when an event that started earlier ends', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse([
            calendarEvent('Sleepover', '2026-11-16T19:00:00+00:00', '2026-11-17T09:30:00+00:00'),
            calendarEvent('Camping trip', '2026-11-16T18:00:00+00:00', '2026-11-19T10:00:00+00:00'),
        ])]);

        Native::visit('/calendar')
            ->assertSee('Until')
            ->assertSee('9:30 am')
            ->assertSee('All day')
            ->assertDontSee('7:00 pm')
            ->assertDontSee('6:00 pm')
            ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-2026-11-17-0')
            ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-2026-11-17-1');
    });

    it('opens Calendars from its menu', function (): void {
        seedCalendar(sampleEvents(), days: 42);

        Native::visit('/calendar')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-more'
                && collect($node['children'])->where('type', 'top_bar_action')->pluck('props.label')->all() === ['Calendars', 'Refresh'])
            ->assertElement('bottom_bar', fn (array $node): bool => array_column($node['children'][0]['children'], 'ref') === ['calendar-more'])
            ->assertMissingElement('top_bar_action', fn (array $node): bool => ($node['props']['id'] ?? null) === 'calendar-more')
            ->tap('calendar-feeds')
            ->assertNavigatedTo('/calendar/feeds');
    });

    it('tells which calendar is missing and where to fix it', function (): void {
        Saloon::fake([
            GetCalendarFeedsRequest::class => calendarFeedsResponse([calendarFeed(1, 'Family', '#2563eb'), failingWorkFeed()]),
            GetCalendarEventsRequest::class => calendarEventsResponse([sampleEvents()[2]], [workWarning()]),
        ]);

        Native::visit('/calendar')
            ->assertSee('Work isn’t showing')
            ->assertSee('We couldn’t fetch this calendar, so its events are missing. Its link may have expired.')
            ->assertSee('HTTP 401')
            ->assertSee('Maya’s swim lesson')
            ->assertDontSee('Design sync')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-more'
                && $node['props']['a11y_label'] === 'More, a calendar isn’t showing')
            ->tap('calendar-warning-2-fix')
            ->assertNavigatedTo('/calendar/feeds');
    });

    it('shows the last events it fetched while offline and tries again', function (): void {
        seedCalendar(sampleEvents(), days: 42);
        $this->travel(3)->hours();
        Saloon::fake([GetCalendarFeedsRequest::class => calendarOffline(), GetCalendarEventsRequest::class => calendarOffline()]);

        $screen = Native::visit('/calendar')
            ->assertSee('You’re offline')
            ->assertSee('These are the events from this morning at 7:42, so they may be out of date.')
            ->assertSee('Picture day')
            ->assertDontSee('Updated just now');

        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse(sampleEvents())]);

        $screen->tap('calendar-retry')
            ->assertDontSee('You’re offline')
            ->assertDontSee('next 6 weeks');
    });

    it('says Sunny is unavailable when it has nothing to show', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => MockResponse::make([], 500), GetCalendarEventsRequest::class => MockResponse::make([], 500)]);

        Native::visit('/calendar')
            ->assertSee('The server is unavailable')
            ->assertSee('Couldn’t load your events. Try again in a moment.');
    });

    it('invites a team without calendars to add one', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse([]), GetCalendarEventsRequest::class => calendarEventsResponse([])]);

        Native::visit('/calendar')
            ->assertSee('No calendars yet')
            ->assertSee('Connect Google Calendar, Proton or any calendar with an iCal link to see the next six weeks of plans here.')
            ->tap('add-first-calendar')
            ->assertNavigatedTo('/calendar/feeds/create');
    });

    it('says when nothing is coming up in the next six weeks', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse([])]);

        Native::visit('/calendar')->assertSee('Nothing in the next six weeks');
    });
});

describe('Calendars', function (): void {
    it('lists the team’s calendars with the failing one first', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse([calendarFeed(1, 'Family', '#2563eb'), calendarFeed(3, 'School', '#ea580c', ['last_fetched_at' => '2026-11-17T04:42:00.000000Z']), failingWorkFeed()])]);

        Native::visit('/calendar/feeds')
            ->assertNavTitle('Calendars')
            ->assertSee('Their events show on the team’s calendar. Anyone on the team can add, edit or remove them.')
            ->assertSee('https://calendar.example.com/family.ics')
            ->assertDontSee('Updated 12 minutes ago')
            ->assertSee('Updated 3 hours ago')
            ->assertSee('Last failed 2 hours ago')
            ->assertSee('The server couldn’t fetch it')
            ->assertSee('HTTP 401')
            ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-feed-2-card')
            ->assertAccessible()
            ->tap('calendar-feed-2')
            ->assertNavigatedTo('/calendar/feeds/2/edit');
    });

    it('puts a longer problem under the heading and says just now for a fresh failure', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse([calendarFeed(5, 'Camp', '#ca8a04', [
            'status' => 'failing',
            'last_failed_at' => '2026-11-17T07:41:40.000000Z',
            'last_error' => 'Could not connect to the calendar server.',
        ])])]);

        Native::visit('/calendar/feeds')
            ->assertSee('Last failed just now')
            ->assertSee('Could not connect to the calendar server')
            ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-feed-5-problem');
    });

    it('shows the calendars it last fetched while offline', function (): void {
        seedCalendar([]);
        Saloon::fake([GetCalendarFeedsRequest::class => calendarOffline()]);

        Native::visit('/calendar/feeds')
            ->assertSee('You’re offline')
            ->assertSee('These may be out of date. Try again when you’re connected.')
            ->assertSee('Family')
            ->assertSee('School');
    });

    it('adds a calendar', function (): void {
        seedCalendar(sampleEvents());
        Saloon::fake([SaveCalendarFeedRequest::class => MockResponse::make(['data' => calendarFeed(4, 'Soccer', '#dc2626')], 201)]);

        Native::visit('/calendar/feeds')
            ->tap('add-calendar')
            ->assertNavigatedTo('/calendar/feeds/create')
            ->follow()
            ->assertNavTitle('New calendar')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-calendar-submit' && str_contains(json_encode($node), '"text":"Create calendar"'))
            ->assertDontSee('Needs a connection')
            ->set('name', ' Soccer ')
            ->set('url', 'webcal://calendar.example.com/soccer.ics')
            ->tap('create-calendar-color-2')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-calendar-color-2'
                && ($node['props']['a11y_label'] ?? $node['a11y_label'] ?? null) === 'Red, selected')
            ->tap('create-calendar-submit')
            ->assertWentBack();

        Saloon::assertSent(fn (SaveCalendarFeedRequest $request): bool => $request->getMethod()->value === 'POST'
            && $request->resolveEndpoint() === '/teams/family/calendar-feeds'
            && collect($request->body()->all())->except('client_uuid')->all() === ['name' => 'Soccer', 'url' => 'https://calendar.example.com/soccer.ics', 'color' => '#dc2626']
            && Str::isUuid($request->body()->all()['client_uuid']));
        expect(collect(app(SunnyCalendar::class)->feeds(1)['feeds'])->pluck('name')->all())->toBe(['Family', 'School', 'Soccer', 'Work'])
            ->and(app(SunnyCalendar::class)->events(1, '2026-11-17', 2))->toBeNull();
    });

    it('waits long enough for Sunny to fetch a new or changed calendar', function (): void {
        expect((new SaveCalendarFeedRequest('family', []))->config()->all())->toBe(['connect_timeout' => 4, 'timeout' => 15])
            ->and((new SaveCalendarFeedRequest('family', [], 2))->config()->all())->toBe(['connect_timeout' => 4, 'timeout' => 15])
            ->and((new DeleteCalendarFeedRequest('family', 2))->config()->all())->toBe(['connect_timeout' => 4, 'timeout' => 8]);
    });

    it('drops the offline notice once a calendar saves', function (): void {
        seedCalendar([]);
        Saloon::fake([GetCalendarFeedsRequest::class => calendarOffline()]);
        $screen = Native::visit('/calendar/feeds')->assertSee('You’re offline');

        Saloon::fake([
            GetCalendarFeedsRequest::class => calendarFeedsResponse(),
            SaveCalendarFeedRequest::class => MockResponse::make(['data' => calendarFeed(4, 'Soccer', '#dc2626')], 201),
        ]);

        $screen->tap('add-calendar')
            ->follow()
            ->set('name', 'Soccer')
            ->set('url', 'https://calendar.example.com/soccer.ics')
            ->tap('create-calendar-submit')
            ->goBack()
            ->assertDontSee('You’re offline')
            ->assertSee('Family');
    });

    it('opens the calendar forms disabled while offline and frees them when the connection returns', function (string $uri, string $submit): void {
        seedCalendar(sampleEvents(), feeds: [calendarFeed(1, 'Family', '#2563eb'), failingWorkFeed()]);
        Native::fakeBridge()->respondTo('Network.Status', ['connected' => false]);

        $screen = Native::visit($uri)
            ->assertSee('You’re offline')
            ->assertSee('Try again when you’re connected.')
            ->assertDontSee('Needs a connection')
            ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === $submit)
            ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === $submit);

        $screen->bridge()->respondTo('Network.Status', ['connected' => true]);

        $screen->firePolls()
            ->assertDontSee('You’re offline')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === $submit);
    })->with([
        'new calendar' => ['/calendar/feeds/create', 'create-calendar-submit'],
        'edit calendar' => ['/calendar/feeds/2/edit', 'edit-calendar-submit'],
    ]);

    it('says when the calendar was last updated only once that is more than five minutes ago', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse(sampleEvents())]);

        $screen = Native::visit('/calendar')->assertDontSee('next 6 weeks');

        expect($screen->instance()->updatedLabel())->toBeNull();

        $this->travel(12)->minutes();

        expect($screen->instance()->updatedLabel())->toBe('Updated 12 minutes ago · next 6 weeks');
    });

    it('keeps the form when adding a calendar offline, then retries with the same request id', function (): void {
        Saloon::fake([SaveCalendarFeedRequest::class => calendarOffline()]);

        $screen = Native::visit('/calendar/feeds/create')
            ->set('name', 'Soccer')
            ->set('url', 'https://calendar.example.com/soccer.ics')
            ->tap('create-calendar-submit')
            ->assertSee('You’re offline. Try again when you’re connected.')
            ->assertNoNavigation()
            ->assertSet('name', 'Soccer');
        $uuid = $screen->get('clientUuid');

        Saloon::fake([SaveCalendarFeedRequest::class => MockResponse::make(['data' => calendarFeed(4, 'Soccer', '#2563eb', ['client_uuid' => $uuid])], 201)]);

        $screen->tap('create-calendar-submit')->assertWentBack();
        Saloon::assertSent(fn (SaveCalendarFeedRequest $request): bool => $request->body()->all()['client_uuid'] === $uuid);
    });

    it('checks the form before sending it', function (string $name, string $url, string $error): void {
        Saloon::fake([]);

        Native::visit('/calendar/feeds/create')
            ->set('name', $name)
            ->set('url', $url)
            ->tap('create-calendar-submit')
            ->assertSee($error)
            ->assertNoNavigation();

        Saloon::assertNothingSent();
    })->with([
        'no name' => ['  ', 'https://calendar.example.com/a.ics', 'Give the calendar a name.'],
        'no link' => ['Family', '', 'Paste the calendar’s link.'],
        'not a web link' => ['Family', 'ftp://calendar.example.com/a.ics', 'The link needs to be a web address starting with https://.'],
    ]);

    it('shows what Sunny refused', function (): void {
        Saloon::fake([SaveCalendarFeedRequest::class => MockResponse::make(['message' => 'Invalid.', 'errors' => ['url' => ['The url field must be a valid URL.']]], 422)]);

        Native::visit('/calendar/feeds/create')
            ->set('name', 'Family')
            ->set('url', 'https://calendar.example.com/a.ics')
            ->tap('create-calendar-submit')
            ->assertSee('The url field must be a valid URL.')
            ->assertNoNavigation();
    });

    it('edits a failing calendar', function (): void {
        seedCalendar(sampleEvents(), feeds: [calendarFeed(1, 'Family', '#2563eb'), failingWorkFeed()]);
        Saloon::fake([SaveCalendarFeedRequest::class => MockResponse::make(['data' => calendarFeed(2, 'Work', '#16a34a', ['url' => 'https://calendar.example.com/new.ics', 'last_fetched_at' => null])])]);

        Native::visit('/calendar/feeds/2/edit')
            ->assertNavTitle('Edit calendar')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-calendar-submit' && str_contains(json_encode($node), '"text":"Save"'))
            ->assertSee('This calendar isn’t updating')
            ->assertSee('HTTP 401 · last failed 2 hours ago. The link may have been reset. Paste the new one and save.')
            ->assertSet('name', 'Work')
            ->assertSet('url', 'https://calendar.example.com/work.ics')
            ->assertSet('colorIndex', 1)
            ->assertElement('bottom_bar', fn (array $node): bool => array_column($node['children'][0]['children'], 'ref') === ['edit-calendar-submit'])
            ->assertDontSee('Remove Work')
            ->set('url', 'https://calendar.example.com/new.ics')
            ->tap('edit-calendar-submit')
            ->assertWentBack();

        Saloon::assertSent(fn (SaveCalendarFeedRequest $request): bool => $request->getMethod()->value === 'PATCH'
            && $request->resolveEndpoint() === '/teams/family/calendar-feeds/2'
            && $request->body()->all() === ['name' => 'Work', 'url' => 'https://calendar.example.com/new.ics', 'color' => '#16a34a']);
        expect(collect(app(SunnyCalendar::class)->feeds(1)['feeds'])->firstWhere('id', 2)['status'])->toBe('ok');
    });

    it('keeps an edit when offline', function (): void {
        seedCalendar([], feeds: [calendarFeed(1, 'Family', '#2563eb')]);
        Saloon::fake([SaveCalendarFeedRequest::class => calendarOffline()]);

        Native::visit('/calendar/feeds/1/edit')
            ->set('name', 'Home')
            ->tap('edit-calendar-submit')
            ->assertSee('You’re offline. Try again when you’re connected.')
            ->assertNoNavigation();

        expect(app(SunnyCalendar::class)->feeds(1)['feeds'][0]['name'])->toBe('Family');
    });

    it('gives each calendar a more menu with Edit first and Remove last, and leaves Save alone on the edit screen', function (): void {
        seedCalendar(sampleEvents(), feeds: [calendarFeed(1, 'Family', '#2563eb'), failingWorkFeed()]);

        $menu = fn (array $node): array => collect($node['children'])->where('type', 'top_bar_action')
            ->map(fn (array $item): array => [$item['ref'], $item['props']['label'], $item['props']['a11y_label'] ?? null, $item['props']['icon'], $item['props']['destructive'] ?? false])
            ->values()->all();

        Native::visit('/calendar/feeds', platform: 'ios')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-feed-2-more'
                && ! isset($node['style'])
                && $node['layout']['width'] == 48
                && $node['props']['a11y_label'] === 'More for Work'
                && $menu($node) === [
                    ['calendar-feed-2-edit', 'Edit', null, 'pencil', false],
                    ['calendar-feed-2-remove', 'Remove calendar', 'Remove Work', 'trash', true],
                ])
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-feed-1-more'
                && $node['props']['a11y_label'] === 'More for Family'
                && array_column($menu($node), 0) === ['calendar-feed-1-edit', 'calendar-feed-1-remove'])
            ->assertElement('row', fn (array $node): bool => array_column($node['children'] ?? [], 'ref') === ['calendar-feed-2', 'calendar-feed-2-more'])
            ->tap('calendar-feed-2-edit')
            ->assertNavigatedTo('/calendar/feeds/2/edit');

        Native::visit('/calendar/feeds')
            ->tap('calendar-feed-2')
            ->assertNavigatedTo('/calendar/feeds/2/edit');

        Native::visit('/calendar/feeds/2/edit', platform: 'ios')
            ->assertElement('bottom_bar', fn (array $node): bool => array_column($node['children'][0]['children'], 'ref') === ['edit-calendar-submit'])
            ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-calendar-more')
            ->assertMissingElement('top_bar_action', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-calendar-remove');

        Native::visit('/calendar/feeds/create')
            ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-calendar-more');
    });

    it('removes a calendar from the list once confirmed', function (): void {
        seedCalendar(sampleEvents(), feeds: [calendarFeed(1, 'Family', '#2563eb'), failingWorkFeed()]);
        $fetches = 0;
        Saloon::fake([
            DeleteCalendarFeedRequest::class => MockResponse::make([], 204),
            GetCalendarFeedsRequest::class => function () use (&$fetches): MockResponse {
                return calendarFeedsResponse(++$fetches === 1 ? [calendarFeed(1, 'Family', '#2563eb'), failingWorkFeed()] : [calendarFeed(1, 'Family', '#2563eb')]);
            },
        ]);

        Native::visit('/calendar/feeds')
            ->assertSee('Work')
            ->tap('calendar-feed-2-remove')
            ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'remove-calendar'
                && $params['title'] === 'Remove “Work”?'
                && $params['message'] === 'Its events will stop showing for everyone on your team.')
            ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Remove', 'id' => 'remove-calendar'])
            ->assertNoNavigation()
            ->assertDontSee('Work')
            ->assertSee('Family')
            ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'calendar-feed-2');

        expect($fetches)->toBe(2);
        Saloon::assertSent(fn (DeleteCalendarFeedRequest $request): bool => $request->resolveEndpoint() === '/teams/family/calendar-feeds/2');
        expect(collect(app(SunnyCalendar::class)->feeds(1)['feeds'])->pluck('name')->all())->toBe(['Family'])
            ->and(app(SunnyCalendar::class)->events(1, '2026-11-17', 2))->toBeNull();
    });

    it('keeps a calendar when removing it offline or cancelling', function (): void {
        seedCalendar([], feeds: [calendarFeed(1, 'Family', '#2563eb')]);
        Saloon::fake([
            DeleteCalendarFeedRequest::class => calendarOffline(),
            GetCalendarFeedsRequest::class => calendarFeedsResponse([calendarFeed(1, 'Family', '#2563eb')]),
        ]);

        Native::visit('/calendar/feeds')
            ->tap('calendar-feed-1-remove')
            ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'remove-calendar'])
            ->assertNoNavigation()
            ->assertNativeNotCalled('ToastKit.Show')
            ->assertSet('removingId', null)
            ->tap('calendar-feed-1-remove')
            ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Remove', 'id' => 'remove-calendar'])
            ->assertToastShown(fn (array $params): bool => $params['message'] === 'You’re offline. Try again when you’re connected.')
            ->assertSee('Family')
            ->assertNoNavigation();

        Saloon::assertNotSent(DeleteCalendarFeedRequest::class);
        expect(app(SunnyCalendar::class)->feeds(1)['feeds'])->toHaveCount(1);
    });

    it('says why a calendar could not be removed when the server refuses', function (): void {
        seedCalendar([], feeds: [calendarFeed(1, 'Family', '#2563eb')]);
        Saloon::fake([
            DeleteCalendarFeedRequest::class => MockResponse::make(['message' => 'Server Error'], 500),
            GetCalendarFeedsRequest::class => calendarFeedsResponse([calendarFeed(1, 'Family', '#2563eb')]),
        ]);

        Native::visit('/calendar/feeds')
            ->tap('calendar-feed-1-remove')
            ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Remove', 'id' => 'remove-calendar'])
            ->assertToastShown(fn (array $params): bool => $params['message'] === 'The server couldn’t remove it right now. Try again in a moment.')
            ->assertSee('Family');

        expect(app(SunnyCalendar::class)->feeds(1)['feeds'])->toHaveCount(1);
    });
});

describe('Today', function (): void {
    it('shows today’s events above the routines', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse(sampleEvents())]);

        Native::visit('/today')
            ->assertSee('On the calendar')
            ->assertSee('Picture day')
            ->assertSee('Design sync')
            ->assertSee('Maya’s swim lesson')
            ->assertSee('Get ready')
            ->assertAccessible()
            ->tap('day-events-calendar')
            ->assertNavigatedTo('/calendar');

        Saloon::assertSent(fn (GetCalendarEventsRequest $request): bool => $request->query()->all() === ['from' => '2026-11-17', 'days' => 1]);
    });

    it('shows the events of the day being viewed', function (): void {
        Saloon::fake([
            GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []]),
            GetCalendarFeedsRequest::class => calendarFeedsResponse(),
            GetCalendarEventsRequest::class => calendarEventsResponse(sampleEvents()),
        ]);
        $screen = Native::visit('/today')->assertSee('Picture day');

        Saloon::fake([
            GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []]),
            GetCalendarFeedsRequest::class => calendarFeedsResponse(),
            GetCalendarEventsRequest::class => calendarEventsResponse([calendarEvent('Dentist', '2026-11-16T14:00:00+00:00', '2026-11-16T15:00:00+00:00')]),
        ]);

        $screen->tap('previous-day')
            ->assertNavTitle('Yesterday')
            ->assertSee('Dentist')
            ->assertSee('2:00')
            ->assertSee('3:00 pm')
            ->assertDontSee('Picture day');

        Saloon::assertSent(fn (GetCalendarEventsRequest $request): bool => $request->query()->all() === ['from' => '2026-11-16', 'days' => 1]);

        $screen->tap('next-day')->assertSee('Picture day');
        expect(collect(Saloon::mockClient()->getRecordedResponses())->filter(fn ($response): bool => $response->getRequest() instanceof GetCalendarEventsRequest
            && $response->getRequest()->query()->get('from') === '2026-11-17'))->toHaveCount(1);
    });

    it('says quietly when nothing is scheduled', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse(), GetCalendarEventsRequest::class => calendarEventsResponse([])]);

        Native::visit('/today')
            ->assertSee('On the calendar')
            ->assertSee('Nothing scheduled')
            ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'day-events-empty');
    });

    it('leaves the calendar out for a team without calendars', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarFeedsResponse([]), GetCalendarEventsRequest::class => calendarEventsResponse([])]);

        Native::visit('/today')
            ->assertDontSee('On the calendar')
            ->assertSee('Get ready');
    });

    it('shows the day’s last fetched events while offline', function (): void {
        seedCalendar(sampleEvents(), days: 1);
        $this->travel(1)->hours();
        Saloon::fake([GetCalendarFeedsRequest::class => calendarOffline(), GetCalendarEventsRequest::class => calendarOffline()]);

        Native::visit('/today')
            ->assertSee('Picture day')
            ->assertSee('You’re offline. Last updated this morning at 7:42.');
    });

    it('leaves the calendar out offline when it has never fetched the day', function (): void {
        Saloon::fake([GetCalendarFeedsRequest::class => calendarOffline(), GetCalendarEventsRequest::class => calendarOffline()]);

        Native::visit('/today')
            ->assertDontSee('On the calendar')
            ->assertSee('Get ready');
    });
});

it('forgets cached calendars when local data is cleared', function (): void {
    seedCalendar(sampleEvents());

    app(SunnyStore::class)->clear();

    expect(DB::table('calendar_snapshots')->count())->toBe(0);
});
