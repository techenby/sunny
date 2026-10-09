<?php

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\NativeComponents\CreateRoutine;
use App\NativeComponents\EditRoutine;
use App\NativeComponents\Routines;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('opens every routine from today’s routines', function () {
    Native::visit('/today')
        ->tap('all-routines')
        ->assertNavigatedTo('/routines')
        ->follow()
        ->assertScreen(Routines::class)
        ->assertNavTitle('All routines');
});

it('lists every routine by time of day with its schedule', function () {
    $screen = Native::visit('/routines')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Get ready'
            && ($node['props']['supporting'] ?? null) === 'Morning · Every day · 3 steps')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Bedtime'
            && ($node['props']['supporting'] ?? null) === 'Evening · Sun, Mon, Tue, Wed, Thu · 2 steps')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Take out trash'
            && ($node['props']['supporting'] ?? null) === 'Anytime · Monthly on the 2nd · Paused · 0 steps')
        ->assertAccessible();

    expect(array_column($screen->get('routines'), 'name'))->toBe(['Get ready', 'Bedtime', 'Take out trash']);
});

it('filters routines by name as you search', function () {
    Native::visit('/routines')
        ->input('updateSearch', ' BED ')
        ->assertSee('Bedtime')
        ->assertDontSee('Get ready')
        ->input('updateSearch', 'laundry')
        ->assertSee('No routines match “laundry”');
});

it('explains how to start when the team has no routines', function () {
    Routine::query()->delete();

    Native::visit('/routines')->assertSee('No routines yet');
});

it('shows a routine’s schedule and steps in order', function () {
    $screen = Native::visit('/routines/1')
        ->assertNavTitle('Get ready')
        ->assertSee('Morning · Every day')
        ->assertAccessible();

    expect(array_column($screen->get('steps'), 'name'))->toBe(['Make bed', 'Feed the cat', 'Water plants']);
});

it('shows a routine without steps', function () {
    Native::visit('/routines/3')->assertSee('This routine has no steps.');
});

it('shows a missing routine', function () {
    Native::visit('/routines/99')->assertSee('This routine could not be found.');
});

it('adds a step to the end of a routine', function () {
    $screen = Native::visit('/routines/2')
        ->submit('routine-new-step', '  Read a story ')
        ->assertSee('Read a story')
        ->assertSet('newStep', '');

    expect(array_column($screen->get('steps'), 'name'))->toBe(['Pajamas', 'Brush teeth', 'Read a story'])
        ->and(RoutineStep::find(-1)->only('routine_id', 'name', 'position'))->toBe(['routine_id' => 2, 'name' => 'Read a story', 'position' => 3])
        ->and(PendingWrite::sole()->only('resource', 'payload'))->toBe(['resource' => 'routine_steps', 'payload' => ['routine_id' => 2, 'name' => 'Read a story']]);
});

it('ignores a blank step and rejects one that is too long', function () {
    Native::visit('/routines/2')
        ->submit('routine-new-step', '   ')
        ->submit('routine-new-step', str_repeat('a', 256))
        ->assertSee('The step is too long (255 characters max).');

    expect(PendingWrite::count())->toBe(0);
});

it('removes a step and queues its deletion', function () {
    $screen = Native::visit('/routines/2')->tap('routine-step-22-remove')->assertDontSee('Brush teeth');

    expect(RoutineStep::find(22))->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes', 'payload'))->toBe(['resource' => 'routine_steps', 'record_id' => 22, 'deletes' => true, 'payload' => ['routine_id' => 2]])
        ->and(array_column($screen->get('steps'), 'name'))->toBe(['Pajamas']);
});

it('removes a step that never reached Sunny without queueing anything', function () {
    Native::visit('/routines/2')->submit('routine-new-step', 'Story')->tap('routine-step--1-remove')->assertDontSee('Story');

    expect(RoutineStep::find(-1))->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('asks before deleting a routine', function () {
    Native::visit('/routines/1')
        ->tap('delete-routine')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'delete-routine')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-routine'])
        ->assertNoNavigation();

    expect(Routine::find(1))->not->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('deletes a routine with its steps and today’s progress once confirmed', function () {
    Native::visit('/today')->tap('routine-step-12');

    Native::visit('/routines/1')
        ->tap('delete-routine')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-routine'])
        ->assertWentBack();

    expect(Routine::find(1))->toBeNull()
        ->and(RoutineStep::where('routine_id', 1)->count())->toBe(0)
        ->and(RoutineOccurrence::where('routine_id', 1)->count())->toBe(0)
        ->and(RoutineOccurrenceStep::find(12))->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes'))->toBe(['resource' => 'routines', 'record_id' => 1, 'deletes' => true]);
    Native::visit('/today')->assertDontSee('Get ready');
});

it('opens the create screen from every routine', function () {
    Native::visit('/routines')
        ->tap('New routine')
        ->assertNavigatedTo('/routines/create')
        ->follow()
        ->assertScreen(CreateRoutine::class)
        ->assertNavTitle('New routine')
        ->assertSet('timeOfDayOptions', ['Morning', 'Afternoon', 'Evening', 'Anytime'])
        ->assertSet('frequencyOptions', ['Daily', 'Weekly', 'Monthly'])
        ->assertDontSee('On these days')
        ->assertAccessible();
});

it('creates a weekly routine on the phone and opens it', function () {
    Native::visit('/routines/create')
        ->input('create-routine-name', ' Gym ')
        ->set('timeOfDayIndex', 1)
        ->set('frequencyIndex', 1)
        ->assertSee('On these days')
        ->toggle('create-routine-weekday-5', true)
        ->toggle('create-routine-weekday-1', true)
        ->toggle('create-routine-weekday-3', true)
        ->toggle('create-routine-weekday-5', false)
        ->tap('create-routine-submit')
        ->assertSet('error', '')
        ->assertReplacedWith('/routines/-1');

    expect(Routine::find(-1)->only('team_id', 'name', 'time_of_day', 'frequency', 'weekdays', 'is_active'))->toBe([
        'team_id' => 1, 'name' => 'Gym', 'time_of_day' => TimeOfDay::Afternoon, 'frequency' => RoutineFrequency::Weekly, 'weekdays' => [1, 3], 'is_active' => true,
    ])->and(PendingWrite::sole()->payload)->toBe([
        'name' => 'Gym', 'time_of_day' => 'afternoon', 'frequency' => 'weekly', 'weekdays' => [1, 3], 'day_of_month' => null, 'is_active' => true,
    ]);
    Native::visit('/routines/-1')
        ->assertSee('Afternoon · Mon, Wed')
        ->assertSee('This routine has no steps.')
        ->assertSee('Saved on this phone · syncing with Sunny');
});

it('creates a paused monthly routine', function () {
    Native::visit('/routines/create')
        ->set('name', 'Pay bills')
        ->set('frequencyIndex', 2)
        ->set('dayOfMonth', ' 31 ')
        ->set('isActive', false)
        ->tap('create-routine-submit')
        ->assertReplacedWith('/routines/-1');

    expect(PendingWrite::sole()->payload)->toMatchArray(['frequency' => 'monthly', 'weekdays' => null, 'day_of_month' => 31, 'is_active' => false]);
    Native::visit('/routines/-1')->assertSee('Morning · Monthly on the 31st · Paused');
});

it('refuses to save an incomplete routine', function (array $changes, string $message) {
    $screen = Native::visit('/routines/create')->set('name', 'Chores');

    foreach ($changes as $property => $value) {
        $screen->set($property, $value);
    }

    $screen->tap('create-routine-submit')->assertNoNavigation()->assertSee($message);

    expect(PendingWrite::count())->toBe(0);
})->with([
    'blank name' => [['name' => '   '], 'Give the routine a name.'],
    'long name' => [['name' => str_repeat('a', 256)], 'The name is too long (255 characters max).'],
    'weekly without days' => [['frequencyIndex' => 1], 'Choose at least one day.'],
    'monthly without a day' => [['frequencyIndex' => 2], 'Enter a day of the month from 1 to 31.'],
    'monthly past the 31st' => [['frequencyIndex' => 2, 'dayOfMonth' => '32'], 'Enter a day of the month from 1 to 31.'],
]);

it('edits a routine and shows the change on today’s routines', function () {
    Native::visit('/routines/2')
        ->tap('edit-routine')
        ->assertNavigatedTo('/routines/2/edit')
        ->follow()
        ->assertScreen(EditRoutine::class)
        ->assertSet('name', 'Bedtime')
        ->assertSet('timeOfDayIndex', 2)
        ->assertSet('frequencyIndex', 1)
        ->assertSet('weekdays', [0, 1, 2, 3, 4])
        ->set('name', 'Lights out')
        ->set('frequencyIndex', 0)
        ->tap('edit-routine-submit')
        ->assertWentBack();

    expect(Routine::find(2)->only('name', 'frequency', 'weekdays', 'user_id'))->toBe(['name' => 'Lights out', 'frequency' => RoutineFrequency::Daily, 'weekdays' => null, 'user_id' => 7])
        ->and(PendingWrite::sole()->payload)->toBe([
            'name' => 'Lights out', 'time_of_day' => 'evening', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'is_active' => true,
        ]);
    Native::visit('/today')->assertSee('Lights out')->assertDontSee('Bedtime');
});

it('fills in a monthly routine’s day when editing', function () {
    Native::visit('/routines/3/edit')
        ->assertSet('frequencyIndex', 2)
        ->assertSet('dayOfMonth', '2')
        ->assertSet('isActive', false)
        ->assertSee('Day of the month');
});

it('shows a missing routine when editing', function () {
    Native::visit('/routines/99/edit')->assertSee('This routine could not be found.');
});
