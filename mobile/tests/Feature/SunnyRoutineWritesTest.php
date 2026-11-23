<?php

use App\Enums\RoutineFrequency;
use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

function routineSnapshot(array $routines, array $steps): array
{
    return [
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [],
        'routines' => $routines, 'routine_steps' => $steps, 'routine_occurrences' => [],
        'synced_at' => now()->toIso8601String(),
    ];
}

it('sends a new routine, then the steps added to it once it has an id', function (): void {
    $nextId = 100;
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use (&$nextId): MockResponse {
        $body = $request->body()->all();
        $owner = str_contains($request->getUrl(), '/steps') ? ['routine_id' => 100, 'position' => 1] : ['team_id' => 1, 'starts_on' => '2026-10-01T00:00:00.000000Z'];

        return MockResponse::make(['data' => [...$body, ...$owner, 'id' => $nextId++, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]], 201);
    }]);
    $outbox = app(SunnyOutbox::class);
    $routine = $outbox->queue('routines', 1, ['name' => 'Gym', 'time_of_day' => 'afternoon', 'frequency' => 'weekly', 'weekdays' => [1, 3], 'day_of_month' => null, 'starts_on' => '2026-10-01', 'is_active' => true]);
    $outbox->queue('routine_steps', 1, ['routine_id' => $routine, 'name' => 'Stretch']);

    expect(Routine::find($routine)->starts_on)->toBe('2026-10-01');

    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines'
        && $request->body()->get('weekdays') === [1, 3]
        && $request->body()->get('starts_on') === '2026-10-01'
        && $request->body()->get('client_uuid') !== null);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines/100/steps'
        && $request->getMethod() === Method::POST
        && $request->body()->all() === ['name' => 'Stretch', 'client_uuid' => $request->body()->get('client_uuid')]);
    expect(Routine::find(100)->local_id)->toBe($routine)
        ->and(Routine::find(100)->starts_on)->toBe('2026-10-01T00:00:00.000000Z')
        ->and(RoutineStep::find(101)->routine_id)->toBe(100)
        ->and(PendingWrite::count())->toBe(0);
    Native::visit('/routines/'.$routine)->assertSee('Stretch');
});

it('waits to send a step until its new routine has an id', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 503)]);
    $outbox = app(SunnyOutbox::class);
    $routine = $outbox->queue('routines', 1, ['name' => 'Gym', 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true]);
    $outbox->queue('routine_steps', 1, ['routine_id' => $routine, 'name' => 'Stretch']);

    expect(fn () => app(SunnyWrites::class)->push())->toThrow(Exception::class);

    Saloon::assertSentCount(1);
    expect(PendingWrite::count())->toBe(2);
});

it('updates a routine with a patch', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...Routine::find(2)->toArray(), 'name' => 'Lights out', 'team_id' => 1]])]);

    app(SunnyOutbox::class)->queue('routines', 1, ['name' => 'Lights out'], 2);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines/2'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['name' => 'Lights out']);
    expect(Routine::find(2)->name)->toBe('Lights out')->and(PendingWrite::count())->toBe(0);
});

it('sends a new owner, or none for the household, in a routine’s patch', function (?int $userId): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...Routine::find(2)->toArray(), 'user_id' => $userId, 'team_id' => 1]])]);

    app(SunnyOutbox::class)->queue('routines', 1, ['name' => 'Bedtime', 'user_id' => $userId], 2);
    expect(Routine::find(2)->user_id)->toBe($userId);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines/2'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['name' => 'Bedtime', 'user_id' => $userId]);
    expect(Routine::find(2)->user_id)->toBe($userId)->and(PendingWrite::count())->toBe(0);
})->with([
    'a member' => [1],
    'the household' => [null],
]);

it('removes a step with a delete under its routine', function (): void {
    Saloon::fake([DeleteRecordRequest::class => MockResponse::make([], 204)]);

    app(SunnyOutbox::class)->delete('routine_steps', 1, 22);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (DeleteRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines/2/steps/22'
        && $request->getMethod() === Method::DELETE);
    expect(PendingWrite::count())->toBe(0);
});

it('drops a new routine’s queued steps when the routine is discarded', function (): void {
    $outbox = app(SunnyOutbox::class);
    $routine = $outbox->queue('routines', 1, ['name' => 'Gym', 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true]);
    $outbox->queue('routine_steps', 1, ['routine_id' => $routine, 'name' => 'Stretch']);

    $outbox->discard('routines', $routine);

    expect(Routine::find($routine))->toBeNull()
        ->and(RoutineStep::where('routine_id', $routine)->count())->toBe(0)
        ->and(PendingWrite::count())->toBe(0);
});

it('refuses a step for a routine that is no longer on the phone', function (): void {
    app(SunnyOutbox::class)->queue('routine_steps', 1, ['routine_id' => 99, 'name' => 'Stretch']);
})->throws(ValidationException::class, 'This routine is no longer available.');

it('downloads routines and their steps, leaving out deleted ones', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make(routineSnapshot(
        [
            ['id' => 40, 'team_id' => 1, 'user_id' => null, 'name' => 'Laundry', 'time_of_day' => 'anytime', 'frequency' => 'monthly', 'weekdays' => null, 'day_of_month' => 3, 'starts_on' => '2026-09-01T00:00:00.000000Z', 'is_active' => true, 'schedule_summary' => 'Monthly on the 3rd', 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()],
            ['id' => 41, 'team_id' => 1, 'user_id' => null, 'name' => 'Old', 'time_of_day' => 'anytime', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'starts_on' => '2026-09-01T00:00:00.000000Z', 'is_active' => true, 'deleted_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()],
        ],
        [
            ['id' => 50, 'routine_id' => 40, 'name' => 'Wash', 'position' => 1, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()],
            ['id' => 51, 'routine_id' => 40, 'name' => 'Fold', 'position' => 2, 'deleted_at' => now()->toIso8601String(), 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()],
        ],
    ))]);

    app(SunnySync::class)->sync();

    expect(Routine::pluck('id')->all())->toBe([40])
        ->and(Routine::find(40)->frequency)->toBe(RoutineFrequency::Monthly)
        ->and(RoutineStep::pluck('name')->all())->toBe(['Wash']);
    Native::visit('/routines')->assertSee('Laundry')->assertDontSee('Get ready');
});

it('keeps routines and steps that have not reached Sunny through a download', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make(routineSnapshot([], []))]);
    $outbox = app(SunnyOutbox::class);
    $routine = $outbox->queue('routines', 1, ['name' => 'Gym', 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true]);
    $step = $outbox->queue('routine_steps', 1, ['routine_id' => $routine, 'name' => 'Stretch']);

    app(SunnySync::class)->sync();

    expect(Routine::find($routine))->not->toBeNull()
        ->and(RoutineStep::find($step))->not->toBeNull()
        ->and(Routine::find(1))->toBeNull();
});

function fakeRoutineSaves(array &$sent): void
{
    $nextId = 100;
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use (&$sent, &$nextId): MockResponse {
        $endpoint = $request->getRequest()->resolveEndpoint();
        $body = $request->body()->all();
        $sent[] = $request->getMethod()->value.' '.$endpoint.' '.json_encode(Arr::except($body, 'client_uuid'));
        $isStep = str_contains($endpoint, '/steps');
        $id = preg_match('#/(\d+)$#', $endpoint, $match) ? (int) $match[1] : $nextId++;
        $owner = $isStep ? ['routine_id' => (int) explode('/', $endpoint)[4], 'position' => 1] : ['team_id' => 1, 'starts_on' => '2026-10-01T00:00:00.000000Z'];
        $record = $isStep ? [] : Arr::except(Routine::find($id)?->toArray() ?? [], ['server', 'local_id']);

        return MockResponse::make(['data' => [...$record, ...Arr::except($body, 'step_order'), ...$owner, 'id' => $id, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]], $isStep || $record === [] ? 201 : 200);
    }]);
}

it('sends a new step order with a patch to the routine', function (): void {
    $sent = [];
    fakeRoutineSaves($sent);

    app(SunnyOutbox::class)->queue('routines', 1, ['step_order' => [13, 11, 12]], 1);
    app(SunnyWrites::class)->push();

    expect($sent)->toBe(['PATCH /teams/family/routines/1 {"step_order":[13,11,12]}'])
        ->and(PendingWrite::count())->toBe(0);
});

it('keeps a step order queued offline, the latest order replacing an earlier one', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 503)]);
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('routines', 1, ['step_order' => [13, 11, 12]], 1);

    expect(fn () => app(SunnyWrites::class)->push())->toThrow(Exception::class);

    $outbox->queue('routines', 1, ['step_order' => [12, 13, 11]], 1);

    expect(PendingWrite::sole()->only('resource', 'record_id', 'payload', 'error'))->toBe(['resource' => 'routines', 'record_id' => 1, 'payload' => ['step_order' => [12, 13, 11]], 'error' => null]);
});

it('waits for a new step to reach Sunny before sending an order that includes it', function (): void {
    $sent = [];
    fakeRoutineSaves($sent);
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('routines', 1, ['name' => 'Lights out'], 2);
    $step = $outbox->queue('routine_steps', 1, ['routine_id' => 2, 'name' => 'Read a story']);
    $outbox->queue('routines', 1, ['step_order' => [21, $step, 22]], 2);

    app(SunnyWrites::class)->push();

    expect($sent)->toBe([
        'POST /teams/family/routines/2/steps {"name":"Read a story"}',
        'PATCH /teams/family/routines/2 {"name":"Lights out","step_order":[21,100,22]}',
    ])->and(PendingWrite::count())->toBe(0);
});

it('creates a routine and its steps before sending the order they were put in', function (): void {
    $sent = [];
    fakeRoutineSaves($sent);
    $outbox = app(SunnyOutbox::class);
    $routine = $outbox->queue('routines', 1, ['name' => 'Gym', 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true]);
    $stretch = $outbox->queue('routine_steps', 1, ['routine_id' => $routine, 'name' => 'Stretch']);
    $lift = $outbox->queue('routine_steps', 1, ['routine_id' => $routine, 'name' => 'Lift']);
    $outbox->queue('routines', 1, ['step_order' => [$lift, $stretch]], $routine);

    app(SunnyWrites::class)->push();

    expect($sent)->toBe([
        'POST /teams/family/routines {"name":"Gym","time_of_day":"morning","frequency":"daily","weekdays":null,"day_of_month":null,"is_active":true}',
        'POST /teams/family/routines/100/steps {"name":"Stretch"}',
        'POST /teams/family/routines/100/steps {"name":"Lift"}',
        'PATCH /teams/family/routines/100 {"step_order":[102,101]}',
    ])->and(PendingWrite::count())->toBe(0);
});
