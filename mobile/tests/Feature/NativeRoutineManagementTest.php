<?php

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
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
        ->assertSee('Morning · Every day · 3 steps')
        ->assertSee('Evening · Sun, Mon, Tue, Wed, Thu · 2 steps')
        ->assertSee('Anytime · Monthly on the 2nd · Paused · 0 steps')
        ->assertMissingElement('column', fn (array $node): bool => str_ends_with($node['ref'] ?? '', '-owner'))
        ->assertAccessible();

    expect(array_column($screen->get('routines'), 'name'))->toBe(['Get ready', 'Bedtime', 'Take out trash']);
});

it('shows who each routine is assigned to when the team has several members', function () {
    seedSunnyMembers();
    Routine::find(3)->update(['user_id' => 99]);

    Native::visit('/routines')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'all-routines-2-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Sam Swick')
        ->assertSee('SS')
        ->assertSee('Sam’s · Evening · Sun, Mon, Tue, Wed, Thu · 2 steps')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'all-routines-1-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Household')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'all-routines-3-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Household')
        ->assertSee('Anytime · Monthly on the 2nd · Paused · 0 steps')
        ->assertAccessible();
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

it('leaves out the assignee picker for a team with one member and assigns them new routines', function () {
    Team::find(1)->update(['members' => [['id' => 7, 'name' => 'Sam Swick', 'initials' => 'SS']]]);

    Native::visit('/routines/create')
        ->assertDontSee('Assigned to')
        ->set('name', 'Stretch')
        ->tap('create-routine-submit')
        ->assertReplacedWith('/routines/-1');

    expect(Routine::find(-1)->user_id)->toBe(7)
        ->and(PendingWrite::sole()->payload)->toMatchArray(['user_id' => 7]);
});

it('keeps a routine’s assignee when a team with one member edits it', function () {
    Team::find(1)->update(['members' => [['id' => 7, 'name' => 'Sam Swick', 'initials' => 'SS']]]);

    Native::visit('/routines/1/edit')->assertDontSee('Assigned to')->set('name', 'Wake up')->tap('edit-routine-submit');

    expect(Routine::find(1)->user_id)->toBeNull()
        ->and(PendingWrite::sole()->payload)->not->toHaveKey('user_id');
});

it('offers the household and each member as assignees of a new routine', function () {
    seedSunnyMembers();

    Native::visit('/routines/create')
        ->assertSee('Assigned to')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-routine-owner-0'
            && ($node['props']['a11y_label'] ?? null) === 'Household, selected')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-routine-owner-1'
            && ($node['props']['a11y_label'] ?? null) === 'Andy Swick')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-routine-owner-7'
            && ($node['props']['a11y_label'] ?? null) === 'Sam Swick')
        ->call('chooseOwner', 99)
        ->assertSet('userId', null)
        ->assertAccessible();
});

it('creates a routine for the household or for one member', function (?string $tap, ?int $userId) {
    seedSunnyMembers();

    $screen = Native::visit('/routines/create')->set('name', 'Stretch');
    if ($tap !== null) {
        $screen->tap($tap);
    }
    $screen->tap('create-routine-submit')->assertReplacedWith('/routines/-1');

    expect(Routine::find(-1)->user_id)->toBe($userId)
        ->and(PendingWrite::sole()->payload)->toBe([
            'name' => 'Stretch', 'time_of_day' => 'morning', 'frequency' => 'daily', 'weekdays' => null, 'day_of_month' => null, 'starts_on' => now()->toDateString(), 'is_active' => true, 'user_id' => $userId,
        ]);
})->with([
    'household' => [null, null],
    'a member' => ['create-routine-owner-1', 1],
    'back to the household' => ['create-routine-owner-0', null],
]);

it('changes who a routine is assigned to and shows it on today’s routines', function (int $routineId, string $tap, ?int $userId, string $subtitle) {
    seedSunnyMembers();

    Native::visit("/routines/{$routineId}/edit")
        ->tap($tap)
        ->tap('edit-routine-submit')
        ->assertWentBack();

    expect(Routine::find($routineId)->user_id)->toBe($userId)
        ->and(PendingWrite::sole()->payload)->toMatchArray(['user_id' => $userId]);
    Native::visit('/today')->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === "routine-{$routineId}-subtitle"
        && ($node['props']['text'] ?? null) === $subtitle);
})->with([
    'household to a member' => [1, 'edit-routine-owner-1', 1, 'Andy’s · Morning · 1 of 3'],
    'member to the household' => [2, 'edit-routine-owner-0', null, 'Evening · 0 of 2'],
]);

it('fills in a routine’s assignee when editing, falling back to the household for one who left', function (?int $stored, ?int $expected, string $selected) {
    seedSunnyMembers();
    Routine::find(2)->update(['user_id' => $stored]);

    Native::visit('/routines/2/edit')
        ->assertSet('userId', $expected)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === $selected
            && str_ends_with($node['props']['a11y_label'] ?? '', ', selected'))
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-routine-owner-99');
})->with([
    'a member' => [7, 7, 'edit-routine-owner-7'],
    'the household' => [null, null, 'edit-routine-owner-0'],
    'someone who left' => [99, null, 'edit-routine-owner-0'],
]);
