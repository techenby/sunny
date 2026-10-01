<?php

use App\Http\Integrations\Sunny\Requests\DeleteRoutineRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineStep;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

function savedRoutineResponse(array $routine, int $status = 200): MockResponse
{
    return MockResponse::make(['data' => $routine], $status);
}

it('mirrors routines and their steps from a sync', function (): void {
    expect(Routine::count())->toBe(3)
        ->and(Routine::find(2)->weekdays)->toBe([1, 3, 5])
        ->and(Routine::find(2)->scheduleSummary())->toBe('Mon, Wed, Fri')
        ->and(Routine::find(3)->scheduleSummary())->toBe('Monthly on the 15th')
        ->and(Routine::find(1)->steps->pluck('name')->all())->toBe(['Make bed', 'Feed the cat', 'Water plants']);

    $snapshot = [...sunnyRoutines()];
    $snapshot = array_slice($snapshot, 0, 1);
    $snapshot[0]['steps'] = [['id' => 10, 'routine_id' => 1, 'name' => 'Only step', 'position' => 1]];
    app(SunnyStore::class)->applySnapshot([
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'recipes' => [], 'items' => [], 'routines' => $snapshot, 'routine_occurrences' => [], 'synced_at' => now()->toIso8601String(),
    ]);

    expect(Routine::pluck('id')->all())->toBe([1])
        ->and(RoutineStep::pluck('name')->all())->toBe(['Only step']);
});

it('lists routines on the manage screen and searches them', function (): void {
    Native::visit('/routines/manage')
        ->assertNavTitle('Manage routines')
        ->assertSee('Get ready')
        ->assertSee('Morning · Every day')
        ->assertSee('Evening · Mon, Wed, Fri')
        ->assertSee('Anytime · Monthly on the 15th')
        ->call('updateSearch', 'bed')
        ->assertSee('Bedtime')
        ->assertDontSee('Get ready')
        ->call('updateSearch', 'zzz')
        ->assertSee('No routines match');
});

it('opens the manage screen from the routines tab', function (): void {
    Native::visit('/routines')->tap('manage-routines')->assertNavigatedTo('/routines/manage');
});

it('creates a routine on the phone, then stores what Sunny confirms', function (): void {
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request): MockResponse {
        $body = $request->body()->all();

        return savedRoutineResponse([
            'id' => 90, 'team_id' => 1, 'name' => $body['name'], 'time_of_day' => $body['time_of_day'], 'frequency' => $body['frequency'],
            'weekdays' => $body['weekdays'], 'day_of_month' => null, 'is_active' => true, 'user' => null,
            'steps' => [['id' => 901, 'name' => 'Teeth', 'position' => 1], ['id' => 902, 'name' => 'Floss', 'position' => 2]],
        ], 201);
    }]);

    Native::visit('/routines/create')
        ->set('name', 'Night routine')
        ->set('timeOfDayIndex', 2)
        ->set('frequencyIndex', 1)
        ->call('toggleWeekday', 6)
        ->call('toggleWeekday', 0)
        ->call('addStep')->call('setStepName', 0, 'Teeth')
        ->call('addStep')->call('setStepName', 1, 'Floss')
        ->tap('create-routine-submit')
        ->assertSet('error', '')
        ->assertWentBack();

    Saloon::assertSent(function (SaveRecordRequest $request): bool {
        $body = $request->body()->all();

        return $request->resolveEndpoint() === '/teams/family/routines'
            && $request->getMethod() === Method::POST
            && $body['name'] === 'Night routine'
            && $body['time_of_day'] === 'evening'
            && $body['frequency'] === 'weekly'
            && $body['weekdays'] === [0, 6]
            && $body['steps'] === [['name' => 'Teeth'], ['name' => 'Floss']]
            && isset($body['client_uuid']);
    });

    expect(PendingWrite::count())->toBe(0)
        ->and(Routine::find(90)->name)->toBe('Night routine')
        ->and(Routine::find(90)->steps->pluck('id')->all())->toBe([901, 902])
        ->and(Routine::where('id', '<', 0)->count())->toBe(0);
});

it('keeps a new routine on the phone when Sunny is unreachable', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 500)]);

    Native::visit('/routines/create')->set('name', 'Offline')->call('addStep')->call('setStepName', 0, 'One')->tap('create-routine-submit')->assertWentBack();

    $routine = Routine::where('name', 'Offline')->sole();
    expect($routine->id)->toBeLessThan(0)
        ->and($routine->steps->pluck('name')->all())->toBe(['One'])
        ->and($routine->steps->first()->id)->toBeLessThan(0)
        ->and(PendingWrite::sole()->error)->toBeNull();

    Native::visit('/routines/manage')->assertSee('Offline')->assertSee('Syncing with Sunny');
});

it('validates the form before saving', function (string $field, mixed $value, string $message): void {
    Saloon::fake([]);

    Native::visit('/routines/create')
        ->set('name', 'Valid')
        ->set($field, $value)
        ->tap('create-routine-submit')
        ->assertSet('error', $message);

    expect(PendingWrite::count())->toBe(0);
    Saloon::assertNothingSent();
})->with([
    'blank name' => ['name', '  ', 'Give the routine a name.'],
    'no weekdays' => ['frequencyIndex', 1, 'Choose at least one day of the week.'],
    'no day of month' => ['frequencyIndex', 2, 'Enter a day of the month between 1 and 31.'],
]);

it('reorders and removes steps in the form', function (): void {
    Native::visit('/routines/create')
        ->call('addStep')->call('setStepName', 0, 'A')
        ->call('addStep')->call('setStepName', 1, 'B')
        ->call('addStep')->call('setStepName', 2, 'C')
        ->call('moveStep', 0, 1)
        ->assertSet('steps', [['id' => null, 'name' => 'B'], ['id' => null, 'name' => 'A'], ['id' => null, 'name' => 'C']])
        ->call('moveStep', 2, 1)
        ->call('removeStep', 0)
        ->assertSet('steps', [['id' => null, 'name' => 'A'], ['id' => null, 'name' => 'C']]);
});

it('fills the edit form from the routine and sends changes to Sunny', function (): void {
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request): MockResponse {
        $body = $request->body()->all();

        return savedRoutineResponse([
            'id' => 1, 'team_id' => 1, 'name' => $body['name'], 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null,
            'day_of_month' => null, 'is_active' => false, 'user' => null,
            'steps' => [['id' => 11, 'name' => 'Make the bed', 'position' => 1], ['id' => 50, 'name' => 'Stretch', 'position' => 2]],
        ]);
    }]);

    Native::visit('/routines/1/edit')
        ->assertSet('name', 'Get ready')
        ->assertSet('timeOfDayIndex', 0)
        ->assertSet('frequencyIndex', 0)
        ->assertSet('isActive', true)
        ->assertSet('steps', [['id' => 11, 'name' => 'Make bed'], ['id' => 12, 'name' => 'Feed the cat'], ['id' => 13, 'name' => 'Water plants']])
        ->set('name', 'Get ready fast')
        ->set('isActive', false)
        ->call('setStepName', 0, 'Make the bed')
        ->call('removeStep', 2)->call('removeStep', 1)
        ->call('addStep')->call('setStepName', 1, 'Stretch')
        ->tap('edit-routine-submit')
        ->assertSet('error', '')
        ->assertWentBack();

    Saloon::assertSent(function (SaveRecordRequest $request): bool {
        $body = $request->body()->all();

        return $request->resolveEndpoint() === '/teams/family/routines/1'
            && $request->getMethod() === Method::PATCH
            && $body['name'] === 'Get ready fast'
            && $body['is_active'] === false
            && $body['steps'] === [['id' => 11, 'name' => 'Make the bed'], ['name' => 'Stretch']]
            && ! isset($body['client_uuid']);
    });

    expect(PendingWrite::count())->toBe(0)
        ->and(Routine::find(1)->name)->toBe('Get ready fast')
        ->and(Routine::find(1)->is_active)->toBeFalse()
        ->and(Routine::find(1)->steps->pluck('id')->all())->toBe([11, 50]);
});

it('keeps a refused edit and lets the user discard it', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['message' => 'Nope'], 422)]);

    Native::visit('/routines/1/edit')->set('name', 'Refused')->tap('edit-routine-submit')->assertWentBack();

    expect(PendingWrite::sole()->error)->not->toBeNull()
        ->and(Routine::find(1)->name)->toBe('Refused');

    Native::visit('/routines/1/edit')
        ->assertSee('Not saved to Sunny')
        ->tap('edit-routine-sync-discard')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => str_contains($params['message'], 'routine on Sunny'))
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Discard', 'id' => 'discard-queued-change']);

    expect(PendingWrite::count())->toBe(0)
        ->and(Routine::find(1)->name)->toBe('Refused');
});

it('deletes a routine after confirming, removing it from Sunny and the phone', function (): void {

    Saloon::fake([DeleteRoutineRequest::class => MockResponse::make([], 204)]);

    Native::visit('/routines/1/edit')
        ->tap('edit-routine-delete')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['title'] === 'Delete routine?')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-routine'])
        ->assertWentBack();

    Saloon::assertSent(fn (DeleteRoutineRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routines/1' && $request->getMethod() === Method::DELETE);

    expect(Routine::find(1))->toBeNull()
        ->and(RoutineStep::where('routine_id', 1)->count())->toBe(0)
        ->and(RoutineOccurrence::where('routine_id', 1)->count())->toBe(0)
        ->and(PendingWrite::count())->toBe(0);
});

it('does nothing when the delete is cancelled', function (): void {
    Saloon::fake([]);

    Native::visit('/routines/1/edit')
        ->tap('edit-routine-delete')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-routine']);

    expect(Routine::find(1))->not->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('keeps a deleted routine as a pending delete while Sunny is unreachable', function (): void {
    Saloon::fake([DeleteRoutineRequest::class => MockResponse::make([], 500)]);

    Native::visit('/routines/2/edit')
        ->tap('edit-routine-delete')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-routine']);

    expect(Routine::find(2))->not->toBeNull()
        ->and(PendingWrite::sole()->payload)->toBe(['deleted' => true]);

    Native::visit('/routines/manage')->assertSee('Deleting');
});

it('treats a routine Sunny no longer has as already deleted', function (): void {
    Saloon::fake([DeleteRoutineRequest::class => MockResponse::make([], 404)]);

    app(SunnyOutbox::class)->queueRoutineDelete(3);
    app(SunnyWrites::class)->push();

    expect(Routine::find(3))->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('flags a refused delete so it can be discarded', function (): void {
    Saloon::fake([DeleteRoutineRequest::class => MockResponse::make([], 403)]);

    app(SunnyOutbox::class)->queueRoutineDelete(3);
    app(SunnyWrites::class)->push();

    expect(Routine::find(3))->not->toBeNull()->and(PendingWrite::sole()->error)->not->toBeNull();

    app(SunnyOutbox::class)->discard('routines', 3);

    expect(PendingWrite::count())->toBe(0)->and(Routine::find(3))->not->toBeNull();
});

it('discards an unsaved routine from the phone', function (): void {
    $id = app(SunnyOutbox::class)->queueRoutine(1, [
        'name' => 'Draft', 'time_of_day' => 'anytime', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true,
        'steps' => [['id' => null, 'name' => 'One']],
    ]);

    expect($id)->toBeLessThan(0);

    app(SunnyOutbox::class)->discard('routines', $id);

    expect(Routine::find($id))->toBeNull()->and(RoutineStep::where('routine_id', $id)->count())->toBe(0)->and(PendingWrite::count())->toBe(0);
});

it('removes a deleted routine’s occurrences from today', function (): void {
    app(SunnyOutbox::class)->queueRoutineDelete(1);

    Native::visit('/routines')->assertDontSee('Get ready')->assertSee('Bedtime');
});

it('keeps a routine with unsent changes when a sync arrives', function (): void {
    app(SunnyOutbox::class)->queueRoutine(1, [
        'name' => 'Local name', 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true,
        'steps' => [['id' => 11, 'name' => 'Local step']],
    ], 1);

    seedSunnyData();

    expect(Routine::find(1)->name)->toBe('Local name')
        ->and(Routine::find(1)->steps->pluck('name')->all())->toBe(['Local step']);
});

it('maps new step ids when a newer edit is queued while one is in flight', function (): void {
    $outbox = app(SunnyOutbox::class);
    $payload = fn (array $steps): array => [
        'name' => 'Get ready', 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true, 'steps' => $steps,
    ];
    $outbox->queueRoutine(1, $payload([['id' => 11, 'name' => 'Make bed'], ['id' => null, 'name' => 'New']]), 1);
    $newId = RoutineStep::where('routine_id', 1)->where('id', '<', 0)->sole()->id;

    $outbox->adoptRoutineStepIds(1, [$newId => 77]);

    expect(RoutineStep::find(77)->name)->toBe('New')
        ->and(collect(PendingWrite::sole()->payload['steps'])->pluck('id')->all())->toBe([11, 77]);
});
