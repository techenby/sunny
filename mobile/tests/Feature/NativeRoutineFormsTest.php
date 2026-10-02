<?php

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\NativeComponents\CreateRoutine;
use App\NativeComponents\EditRoutine;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
    app(SunnyStore::class)->rememberAccount(1);
});

it('lists every routine with its schedule under today’s board', function (): void {
    Routine::find(1)->update(['is_active' => false]);

    Native::visit('/routines')
        ->assertSee('All routines')
        ->assertSee('Every day · Household · Paused')
        ->assertSee('Every day · Sam')
        ->assertSee('Take out trash')
        ->assertAccessible();
});

it('opens the create screen from the routines tab', function (): void {
    Native::visit('/routines')
        ->tap('New routine')
        ->assertNavigatedTo('/routines/create')
        ->follow()
        ->assertScreen(CreateRoutine::class)
        ->assertNavTitle('New routine')
        ->assertSee('Time of day')
        ->assertSee('Steps');
});

it('creates a routine on the phone and goes back', function (): void {
    Native::visit('/routines/create')
        ->input('create-routine-name', ' Homework ')
        ->set('timeOfDayIndex', 1)
        ->set('assigneeIndex', 1)
        ->set('frequencyIndex', 1)
        ->call('toggleWeekday', 3)
        ->call('toggleWeekday', 1)
        ->call('addStep', 'Open backpack')
        ->call('addStep', 'Check planner')
        ->tap('create-routine-submit')
        ->assertSet('error', '')
        ->assertWentBack();

    expect(Routine::find(-1)->only('team_id', 'user_id', 'name', 'time_of_day', 'frequency', 'weekdays', 'steps'))->toBe([
        'team_id' => 1, 'user_id' => 1, 'name' => 'Homework', 'time_of_day' => TimeOfDay::Afternoon,
        'frequency' => RoutineFrequency::Weekly, 'weekdays' => [1, 3], 'steps' => [['name' => 'Open backpack'], ['name' => 'Check planner']],
    ])->and(PendingWrite::sole()->payload)->toBe([
        'name' => 'Homework', 'user_id' => 1, 'time_of_day' => 'afternoon', 'frequency' => 'weekly', 'weekdays' => [1, 3],
        'day_of_month' => null, 'is_active' => true, 'steps' => [['name' => 'Open backpack'], ['name' => 'Check planner']],
    ]);
    Native::visit('/routines')->assertSee('Homework')->assertSee('Mon, Wed · Me');
});

it('refuses to save an incomplete routine', function (array $state, string $message): void {
    $screen = Native::visit('/routines/create')->set('name', 'Chores');

    foreach ($state as $property => $value) {
        $screen->set($property, $value);
    }

    $screen->tap('create-routine-submit')
        ->assertNoNavigation()
        ->assertSee($message);
    expect(PendingWrite::count())->toBe(0);
})->with([
    'blank name' => [['name' => '  '], 'Give the routine a name.'],
    'weekly without days' => [['frequencyIndex' => 1], 'Choose at least one day of the week.'],
    'monthly without a day' => [['frequencyIndex' => 2, 'dayOfMonth' => '32'], 'Choose a day of the month from 1 to 31.'],
]);

it('edits a routine and its steps', function (): void {
    Native::visit('/routines')
        ->tap('edit-routine-2')
        ->assertNavigatedTo('/routines/2/edit')
        ->follow()
        ->assertScreen(EditRoutine::class)
        ->assertSet('name', 'Bedtime')
        ->assertSet('timeOfDayIndex', 2)
        ->assertSet('assigneeIndex', 2)
        ->assertSee('Sam')
        ->set('name', 'Lights out')
        ->set('frequencyIndex', 2)
        ->set('dayOfMonth', '15')
        ->call('renameStep', 0, 'Put on pajamas')
        ->call('removeStep', 1)
        ->call('addStep', 'Read a story')
        ->tap('edit-routine-submit')
        ->assertWentBack();

    expect(PendingWrite::sole()->payload)->toBe([
        'name' => 'Lights out', 'user_id' => 7, 'time_of_day' => 'evening', 'frequency' => 'monthly', 'weekdays' => null,
        'day_of_month' => 15, 'is_active' => true, 'steps' => [['id' => 21, 'name' => 'Put on pajamas'], ['name' => 'Read a story']],
    ])->and(Routine::find(2)->scheduleSummary())->toBe('Monthly on the 15th');
});

it('hands a routine to the household', function (): void {
    Native::visit('/routines/2/edit')->set('assigneeIndex', 0)->tap('edit-routine-submit');

    expect(Routine::find(2)->user_id)->toBeNull()
        ->and(PendingWrite::sole()->payload['user_id'])->toBeNull();
});

it('deletes a routine once confirmed and takes it off today’s board', function (): void {
    Native::visit('/routines/1/edit')
        ->tap('delete-routine')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-routine'])
        ->assertWentBack();

    expect(Routine::find(1))->toBeNull()
        ->and(RoutineOccurrence::where('routine_id', 1)->count())->toBe(0)
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes'))->toBe(['resource' => 'routines', 'record_id' => 1, 'deletes' => true]);
    Native::visit('/routines')->assertDontSee('Get ready');
});

it('shows a missing routine', function (): void {
    Native::visit('/routines/99/edit')->assertSee('This routine could not be found.');
});

it('sends a new routine to Sunny and adopts its id and steps', function (): void {
    Saloon::fake([SaveRecordRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make(['data' => [
        ...$request->body()->all(), 'id' => 100, 'team_id' => 1, 'user' => null, 'starts_on' => '2026-10-02T00:00:00.000000Z',
        'steps' => [['id' => 500, 'routine_id' => 100, 'name' => 'Open backpack', 'position' => 1]],
        'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
    ]], 201)]);
    $id = app(SunnyOutbox::class)->queue('routines', 1, [
        'name' => 'Homework', 'user_id' => null, 'time_of_day' => 'afternoon', 'frequency' => 'daily', 'weekdays' => null,
        'day_of_month' => null, 'is_active' => true, 'steps' => [['name' => 'Open backpack']],
    ]);

    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines'
        && $request->getMethod() === Method::POST
        && $request->body()->get('steps') === [['name' => 'Open backpack']]
        && $request->body()->get('client_uuid') !== null);
    expect(Routine::find(100)->local_id)->toBe($id)
        ->and(Routine::find(100)->steps)->toBe([['id' => 500, 'name' => 'Open backpack']])
        ->and(PendingWrite::count())->toBe(0);
});

it('sends a routine deletion to Sunny', function (): void {
    Saloon::fake([DeleteRecordRequest::class => MockResponse::make([], 204)]);

    app(SunnyOutbox::class)->delete('routines', 1, 2);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (DeleteRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines/2');
    expect(PendingWrite::count())->toBe(0);
});

it('forgets a routine that never reached Sunny', function (): void {
    $outbox = app(SunnyOutbox::class);
    $id = $outbox->queue('routines', 1, ['name' => 'Homework', 'time_of_day' => 'anytime', 'frequency' => 'daily', 'steps' => []]);

    $outbox->delete('routines', 1, $id);

    expect(Routine::find($id))->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('downloads routines with their assignee and steps', function (): void {
    expect(Routine::pluck('name', 'id')->all())->toBe([1 => 'Get ready', 2 => 'Bedtime', 3 => 'Take out trash'])
        ->and(Routine::find(2)->only('user_id', 'assignee', 'steps'))->toBe([
            'user_id' => 7, 'assignee' => 'Sam', 'steps' => [['id' => 21, 'name' => 'Pajamas'], ['id' => 22, 'name' => 'Brush teeth']],
        ]);
});
