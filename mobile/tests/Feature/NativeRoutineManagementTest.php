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
    Native::visit('/routines/99')->assertSee('Routine not found');
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

it('renames a step in place and queues the change', function () {
    $screen = Native::visit('/routines/2')
        ->tap('routine-step-22-edit')
        ->assertSet('editingName', 'Brush teeth')
        ->submit('routine-step-22-name', '  Floss and brush ')
        ->assertSee('Floss and brush')
        ->assertSet('editingStepId', null);

    expect(array_column($screen->get('steps'), 'name'))->toBe(['Pajamas', 'Floss and brush'])
        ->and(RoutineStep::find(22)->only('routine_id', 'name', 'position'))->toBe(['routine_id' => 2, 'name' => 'Floss and brush', 'position' => 2])
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'payload'))->toBe(['resource' => 'routine_steps', 'record_id' => 22, 'payload' => ['name' => 'Floss and brush', 'routine_id' => 2]]);
});

it('keeps a step’s name when the edit is blank, unchanged, or too long', function () {
    Native::visit('/routines/2')
        ->tap('routine-step-22-edit')->submit('routine-step-22-name', '   ')->assertSet('editingStepId', null)
        ->tap('routine-step-22-edit')->submit('routine-step-22-name', 'Brush teeth')->assertSet('editingStepId', null)
        ->tap('routine-step-22-edit')->submit('routine-step-22-name', str_repeat('a', 256))
        ->assertSee('The step is too long (255 characters max).')
        ->assertSet('editingStepId', 22);

    expect(RoutineStep::find(22)->name)->toBe('Brush teeth')->and(PendingWrite::count())->toBe(0);
});

it('folds a rename into a step that has not reached Sunny yet', function () {
    Native::visit('/routines/2')
        ->submit('routine-new-step', 'Story')
        ->tap('routine-step--1-edit')
        ->submit('routine-step--1-name', 'Read a story');

    expect(PendingWrite::sole()->only('resource', 'record_id', 'payload'))->toBe(['resource' => 'routine_steps', 'record_id' => -1, 'payload' => ['routine_id' => 2, 'name' => 'Read a story']]);
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

it('starts and stops reordering a routine’s steps', function () {
    Native::visit('/routines/1')
        ->assertSee('Steps (3)')
        ->tap('routine-reorder')
        ->assertSet('reordering', true)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-step-12-up')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-step-12-remove')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-reorder')
        ->assertAccessible()
        ->tap('routine-reorder-done')
        ->assertSet('reordering', false)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-step-12-remove');

    expect(PendingWrite::count())->toBe(0);
});

it('offers reordering only when a routine has two or more steps', function () {
    Native::visit('/routines/3')->assertSee('Steps')->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-reorder');
    Native::visit('/routines/2')->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-reorder');
});

it('moves steps up and down without moving past either end', function () {
    $screen = Native::visit('/routines/1')
        ->tap('routine-reorder')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-step-11-up')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-step-13-down')
        ->tap('routine-step-13-up')
        ->assertSet('movedStepId', 13);

    expect(array_column($screen->get('steps'), 'name'))->toBe(['Make bed', 'Water plants', 'Feed the cat']);

    $screen->call('moveStepUp', 11)->call('moveStepDown', 12)->tap('routine-step-13-up');

    expect(array_column($screen->get('steps'), 'name'))->toBe(['Water plants', 'Make bed', 'Feed the cat'])
        ->and(RoutineStep::find(13)->position)->toBe(3)
        ->and(PendingWrite::count())->toBe(0);
});

it('saves the new order once, when Done is tapped', function () {
    $screen = Native::visit('/routines/1')
        ->tap('routine-reorder')
        ->tap('routine-step-13-up')
        ->tap('routine-step-13-up')
        ->tap('routine-step-11-down')
        ->tap('routine-step-12-down')
        ->tap('routine-reorder-done')
        ->assertSet('reordering', false);

    expect(array_column($screen->get('steps'), 'name'))->toBe(['Water plants', 'Make bed', 'Feed the cat'])
        ->and(RoutineStep::whereIn('id', [11, 12, 13])->orderBy('position')->pluck('position', 'id')->all())->toBe([13 => 1, 11 => 2, 12 => 3])
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'payload'))->toBe(['resource' => 'routines', 'record_id' => 1, 'payload' => ['step_order' => [13, 11, 12]]]);
    expect(array_column(Native::visit('/routines/1')->get('steps'), 'name'))->toBe(['Water plants', 'Make bed', 'Feed the cat']);
});

it('saves nothing when the steps end up in their original order', function () {
    Native::visit('/routines/1')
        ->tap('routine-reorder')
        ->tap('routine-step-12-up')
        ->tap('routine-step-12-down')
        ->tap('routine-reorder-done');

    expect(PendingWrite::count())->toBe(0);
});

it('discards moves when leaving without tapping Done', function () {
    Native::visit('/routines/1')
        ->tap('routine-reorder')
        ->tap('routine-step-13-up')
        ->tap('routine-step-13-up')
        ->call('back')
        ->assertWentBack();

    expect(array_column(Native::visit('/routines/1')->assertSet('reordering', false)->get('steps'), 'name'))->toBe(['Make bed', 'Feed the cat', 'Water plants'])
        ->and(RoutineStep::find(13)->position)->toBe(3)
        ->and(PendingWrite::count())->toBe(0);
});

it('keeps a step added while reordering at the end, and saves its place in the order', function () {
    $screen = Native::visit('/routines/2')
        ->tap('routine-reorder')
        ->submit('routine-new-step', 'Read a story')
        ->tap('routine-step--1-up')
        ->tap('routine-reorder-done');

    expect(array_column($screen->get('steps'), 'name'))->toBe(['Pajamas', 'Read a story', 'Brush teeth'])
        ->and(PendingWrite::where('resource', 'routines')->sole()->payload)->toBe(['step_order' => [21, -1, 22]]);
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

it('offers time of day and repeats as vertical option lists', function () {
    Native::visit('/routines/create')
        ->assertMissingElement('button_group')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-routine-time-of-day-0'
            && ($node['props']['a11y_label'] ?? null) === 'Morning, selected')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-routine-time-of-day-3'
            && ($node['props']['a11y_label'] ?? null) === 'Anytime')
        ->tap('create-routine-time-of-day-3')
        ->assertSet('timeOfDayIndex', 3)
        ->call('chooseTimeOfDay', 9)
        ->assertSet('timeOfDayIndex', 3)
        ->call('chooseFrequency', -1)
        ->assertSet('frequencyIndex', 0);
});

it('creates a weekly routine on the phone and opens it', function () {
    Native::visit('/routines/create')
        ->input('create-routine-name', ' Gym ')
        ->tap('create-routine-time-of-day-1')
        ->tap('create-routine-frequency-1')
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
        'name' => 'Gym', 'time_of_day' => 'afternoon', 'frequency' => 'weekly', 'weekdays' => [1, 3], 'day_of_month' => null, 'starts_on' => now()->toDateString(), 'is_active' => true,
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
            'name' => 'Lights out', 'time_of_day' => 'evening', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'starts_on' => '2026-09-01', 'is_active' => true,
        ]);
    Native::visit('/today')->assertSee('Lights out')->assertDontSee('Bedtime');
});

it('starts a new routine today by default', function () {
    $this->travelTo('2026-11-16 09:00');

    Native::visit('/routines/create')
        ->assertSet('startsOn', '2026-11-16')
        ->assertSet('showStartsOnPicker', false)
        ->assertSee('Starts on')
        ->assertSee('Monday, November 16')
        ->assertMissingElement('date_picker')
        ->assertDontSee('It’ll show up on Today');
});

it('picks a later start date for a new routine and sends it to Sunny', function () {
    $this->travelTo('2026-11-16 09:00');

    Native::visit('/routines/create')
        ->set('name', 'Change the furnace filter')
        ->set('frequencyIndex', 2)
        ->set('dayOfMonth', '1')
        ->tap('create-routine-starts-on')
        ->assertSet('showStartsOnPicker', true)
        ->assertPicker('Starts on', 'date')
        ->assertPickerValue('Starts on', '2026-11-16')
        ->pickDate('create-routine-starts-on-calendar', '2026-12-01')
        ->assertSet('startsOn', '2026-12-01')
        ->assertSet('showStartsOnPicker', false)
        ->assertSee('Tuesday, December 1')
        ->assertSee('It’ll show up on Today from December 1.')
        ->assertAccessible()
        ->tap('create-routine-submit')
        ->assertReplacedWith('/routines/-1');

    expect(Routine::find(-1)->starts_on)->toBe('2026-12-01')
        ->and(PendingWrite::sole()->payload)->toMatchArray(['frequency' => 'monthly', 'day_of_month' => 1, 'starts_on' => '2026-12-01']);
});

it('describes the start date and only hints at dates after today', function (string $date, string $label, ?string $hint) {
    $this->travelTo('2026-11-16 09:00');

    $screen = Native::visit('/routines/create')
        ->call('chooseStartsOn', $date)
        ->assertSee($label);

    $hint === null
        ? $screen->assertMissingElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'create-routine-starts-on-hint')
        : $screen->assertSee($hint);
})->with([
    'today' => ['2026-11-16', 'Monday, November 16', null],
    'in the past' => ['2026-11-01', 'Sunday, November 1', null],
    'tomorrow' => ['2026-11-17', 'Tuesday, November 17', 'It’ll show up on Today from November 17.'],
    'next year' => ['2027-01-05', 'Tuesday, January 5, 2027', 'It’ll show up on Today from January 5, 2027.'],
]);

it('keeps the start date when the picker is dismissed or sends something that isn’t a date', function () {
    Native::visit('/routines/create')
        ->tap('create-routine-starts-on')
        ->call('chooseStartsOn', '2026-02-30')
        ->call('chooseStartsOn', 'tomorrow')
        ->assertSet('startsOn', now()->toDateString())
        ->assertSet('showStartsOnPicker', true)
        ->dismissSheet('create-routine-starts-on-picker')
        ->assertSet('showStartsOnPicker', false)
        ->assertSet('startsOn', now()->toDateString());
});

it('moves a routine’s start date later and hides it from today until then', function () {
    $startsOn = now()->addDays(3);

    Native::visit('/routines/2/edit')
        ->assertSet('startsOn', '2026-09-01')
        ->assertSee('Tuesday, September 1')
        ->tap('edit-routine-starts-on')
        ->assertPickerValue('Starts on', '2026-09-01')
        ->pickDate('edit-routine-starts-on-calendar', $startsOn)
        ->assertSee('It’ll show up on Today from '.$startsOn->format('F j').'.')
        ->tap('edit-routine-submit')
        ->assertWentBack();

    expect(Routine::find(2)->starts_on)->toBe($startsOn->toDateString())
        ->and(PendingWrite::sole()->payload)->toMatchArray(['starts_on' => $startsOn->toDateString()]);
    Native::visit('/today')->assertSee('Get ready')->assertDontSee('Bedtime');
});

it('fills in a monthly routine’s day when editing', function () {
    Native::visit('/routines/3/edit')
        ->assertSet('frequencyIndex', 2)
        ->assertSet('dayOfMonth', '2')
        ->assertSet('isActive', false)
        ->assertSee('Day of the month');
});

it('shows a missing routine when editing', function () {
    Native::visit('/routines/99/edit')->assertSee('Routine not found');
});
