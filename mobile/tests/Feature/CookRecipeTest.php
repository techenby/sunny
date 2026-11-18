<?php

use App\Models\Recipe;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;
use NativePHP\LocalNotifications\Events\PermissionGranted;
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\Capabilities;
use Vos\LiveActivities\Support\ActivityFixture;

beforeEach(function () {
    seedSunnyData();

    Recipe::find(1)->update([
        'servings' => '4 people',
        'ingredients' => [
            ['type' => 'heading', 'text' => 'Dry'],
            ['type' => 'item', 'text' => '2 cups all-purpose flour', 'quantity' => 2, 'quantity_max' => null, 'unit' => 'cup', 'name' => 'all-purpose flour'],
            ['type' => 'item', 'text' => '2 tbsp sugar', 'quantity' => 2, 'quantity_max' => null, 'unit' => 'tbsp', 'name' => 'sugar'],
            ['type' => 'heading', 'text' => 'Wet'],
            ['type' => 'item', 'text' => '2 large eggs', 'quantity' => 2, 'quantity_max' => null, 'unit' => null, 'name' => 'large eggs'],
            ['type' => 'item', 'text' => 'Butter, for the griddle', 'quantity' => null, 'quantity_max' => null, 'unit' => null, 'name' => 'Butter, for the griddle'],
        ],
        'instructions' => [
            ['type' => 'step', 'text' => 'Whisk the flour and sugar together.', 'timers' => []],
            ['type' => 'heading', 'text' => 'Batter'],
            ['type' => 'step', 'text' => 'Beat in the eggs, then rest the batter for 5 minutes.', 'timers' => [['seconds' => 300]]],
            ['type' => 'step', 'text' => 'Cook for 2–3 minutes, then flip and cook 1 minute more.', 'timers' => [['seconds' => 120], ['seconds' => 60]]],
            ['type' => 'step', 'text' => 'Chill overnight, about 12 hours.', 'timers' => [['seconds' => 43200]]],
        ],
    ]);
});

it('opens on the prep tab with the ingredients and no tab bar', function () {
    Native::visit('/recipes/1/cook')
        ->assertNavTitle('Cook mode')
        ->assertSee('Buttermilk Pancakes')
        ->assertSet('servings', 4)
        ->assertSee('0 of 4 ready')
        ->assertSee('DRY')
        ->assertSee('WET')
        ->assertSee('2 cups')
        ->assertSee('all-purpose flour')
        ->assertSee('Butter, for the griddle')
        ->assertSee('Start cooking')
        ->assertDontSee('Whisk the flour and sugar together.')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-ingredient-0')
        ->assertMissingElement('native_root_tabs')
        ->assertAccessible();
});

it('offers cook mode from a recipe that has something to cook', function () {
    Native::visit('/recipes/1')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-recipe');

    Recipe::find(5)->update(['ingredients' => null, 'instructions' => null]);

    Native::visit('/recipes/5')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-recipe');
});

it('scales the amounts with the servings', function () {
    Native::visit('/recipes/1/cook')
        ->tap('cook-more-servings')
        ->tap('cook-more-servings')
        ->assertSet('servings', 6)
        ->assertSee('3 cups')
        ->assertSee('3 Tbsp')
        ->assertSee('Butter, for the griddle')
        ->call('changeServings', -10)
        ->assertSet('servings', 1)
        ->assertSee('1/2 cup')
        ->assertSee('1/2 Tbsp');
});

it('hides the servings control when the recipe has no serving count', function () {
    Recipe::find(1)->update(['servings' => null]);

    Native::visit('/recipes/1/cook')
        ->assertSet('servings', null)
        ->assertMissingElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-servings')
        ->assertSee('2 cups');
});

it('ticks ingredients off', function () {
    Native::visit('/recipes/1/cook')
        ->tap('cook-ingredient-1')
        ->tap('cook-ingredient-4')
        ->assertSet('checked', [1 => '2 cups all-purpose flour', 4 => '2 large eggs'])
        ->assertSee('2 of 4 ready')
        ->assertSee('2/4')
        ->tap('cook-ingredient-1')
        ->assertSet('checked', [4 => '2 large eggs'])
        ->call('toggleIngredient', 0)
        ->assertSet('checked', [4 => '2 large eggs']);
});

it('walks through the steps', function () {
    Native::visit('/recipes/1/cook')
        ->tap('cook-go-to-steps')
        ->assertSet('tab', 1)
        ->assertSee('Whisk the flour and sugar together.')
        ->assertSee('Batter')
        ->assertSee('Current step')
        ->assertSee('Next: step 2')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-previous-step')
        ->tap('cook-next-step')
        ->assertSet('step', 2)
        ->assertSee('2/4')
        ->tap('cook-step-4-select')
        ->assertSet('step', 4)
        ->assertSee('Done cooking')
        ->tap('cook-previous-step')
        ->assertSet('step', 3)
        ->tap('cook-tab-prep')
        ->assertSee('Back to step 3');
});

function openedFromTheRecipe(TestableComponent $screen): TestableComponent
{
    $router = (fn () => $this->nativeRouter)->call($screen->instance());

    (function () {
        array_unshift($this->stack, ['component' => null, 'uri' => '/recipes/1', 'params' => ['id' => '1']]);
    })->call($router);

    return $screen;
}

it('goes back to the recipe after the last step', function () {
    openedFromTheRecipe(Native::visit('/recipes/1/cook'))
        ->call('goToStep', 4)
        ->tap('cook-next-step')
        ->assertWentBack();
});

it('goes back to the recipe from the back button', function () {
    openedFromTheRecipe(Native::visit('/recipes/1/cook'))
        ->pressBack()
        ->assertWentBack();
});

it('opens the recipe when cook mode was opened directly and there is nothing to go back to', function () {
    Native::visit('/recipes/1/cook')
        ->pressBack()
        ->assertReplacedWith('/recipes/1');

    Native::visit('/recipes/1/cook')
        ->call('goToStep', 4)
        ->tap('cook-next-step')
        ->assertReplacedWith('/recipes/1');
});

it('offers timers for a step’s durations, except very long ones', function () {
    Native::visit('/recipes/1/cook')
        ->call('showTab', 1)
        ->assertSee('5:00')
        ->assertSee('2:00')
        ->assertSee('then 1 min')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-step-2-timer')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-step-1-timer')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-step-4-timer');
});

it('counts a timer down, alerts when it ends and offers the next duration', function () {
    $this->freezeSecond();

    $screen = Native::visit('/recipes/1/cook')
        ->call('showTab', 1)
        ->tap('cook-step-3-timer')
        ->assertSet('timerStep', 3)
        ->assertSee('STEP 3 TIMER')
        ->assertSee('Stop timer')
        ->assertSee('left of 2:00');

    $this->travel(45)->seconds();

    $screen->firePoll('tick')
        ->assertSee('1:15')
        ->tap('cook-tab-prep')
        ->assertSee('STEP 3 TIMER');

    $this->travel(75)->seconds();

    $screen->firePoll('tick')
        ->assertSet('timerStep', null)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Step 3 timer is done' && $params['variant'] === 'info')
        ->assertDontSee('STEP 3 TIMER')
        ->tap('cook-tab-cook')
        ->assertSee('1:00')
        ->assertDontSee('then 1 min');
});

it('stops a running timer from the dock', function () {
    Native::visit('/recipes/1/cook')
        ->call('toggleTimer', 2)
        ->assertSet('timerStep', 2)
        ->tap('cook-timer-stop')
        ->assertSet('timerStep', null)
        ->assertSet('timerEndsAt', null)
        ->assertDontSee('STEP 2 TIMER');
});

it('remembers progress and a running timer when the screen is opened again', function () {
    $this->freezeSecond();

    Native::visit('/recipes/1/cook')
        ->tap('cook-more-servings')
        ->tap('cook-ingredient-1')
        ->call('goToStep', 2)
        ->call('toggleTimer', 2);

    $this->travel(60)->seconds();

    Native::visit('/recipes/1/cook')
        ->assertSet('servings', 5)
        ->assertSet('checked', [1 => '2 cups all-purpose flour'])
        ->assertSet('step', 2)
        ->assertSet('tab', 1)
        ->assertSee('STEP 2 TIMER')
        ->assertSee('4:00');
});

it('shows a missing recipe', function () {
    Native::visit('/recipes/999/cook')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'cook-recipe-missing');
});

it('keeps the screen awake while cooking and lets it sleep again afterwards', function () {
    $requests = [];
    Native::fakeBridge()->respondTo('MobileScreen.KeepAwake', function (array $params) use (&$requests): array {
        $requests[] = $params['enabled'];

        return ['enabled' => $params['enabled']];
    });

    $screen = Native::visit('/recipes/1/cook')
        ->assertSet('staysAwake', true)
        ->assertSee('Screen stays on');

    $screen->instance()->unmount();

    expect($requests)->toBe([true, false]);
});

it('says nothing about the screen when it cannot be kept awake', function () {
    Native::visit('/recipes/1/cook')
        ->assertSet('staysAwake', false)
        ->assertDontSee('Screen stays on');
});

it('does not keep the screen awake for a missing recipe', function () {
    Native::fakeBridge()->respondTo('MobileScreen.KeepAwake', fn (array $params): array => ['enabled' => $params['enabled']]);

    Native::visit('/recipes/999/cook')->assertSet('staysAwake', false);
});

it('returns to the top when switching tabs and brings the current step into view', function () {
    $scrollsTo = fn (int $child) => fn (array $node): bool => ($node['ref'] ?? null) === 'cook-recipe'
        && ($node['props']['auto_scroll_to'] ?? null) === $child;

    Native::visit('/recipes/1/cook')
        ->assertElement('scroll_view', $scrollsTo(0))
        ->tap('cook-tab-cook')
        ->assertElement('scroll_view', $scrollsTo(1))
        ->tap('cook-tab-prep')
        ->assertElement('scroll_view', $scrollsTo(0))
        ->call('goToStep', 3)
        ->assertElement('scroll_view', $scrollsTo(5))
        ->tap('cook-next-step')
        ->assertElement('scroll_view', $scrollsTo(6));
});

it('schedules a notification for when a timer ends', function () {
    $this->freezeSecond();

    Native::visit('/recipes/1/cook')->call('toggleTimer', 2);

    Native::fakeBridge()
        ->assertCalled('LocalNotification.RequestPermission')
        ->assertScheduled(fn (array $notification): bool => $notification['id'] === 'cook-timer-1'
            && $notification['title'] === 'Step 2 timer is done'
            && $notification['body'] === 'Buttermilk Pancakes'
            && $notification['url'] === '/recipes/1/cook');
});

it('shows a running timer as a live activity where the phone supports it', function () {
    $this->freezeSecond();
    config(['nativephp.deeplink_scheme' => 'sunnyhome']);

    $bridge = Native::fakeBridge()
        ->withLiveActivitiesCapabilities(Capabilities::fromArray(['platform' => 'ios', 'supported' => true, 'enabled' => true, 'push' => false, 'promoted' => false]))
        ->withLiveActivityStarted(ActivityFixture::make('cook-timer-1', ActivityContent::make('Buttermilk Pancakes'), deepLink: 'sunnyhome://recipes/1/cook'));

    Native::visit('/recipes/1/cook')->call('toggleTimer', 2);

    $bridge
        ->assertLiveActivityStarted('cook-timer-1', fn (ActivityContent $content): bool => $content->toArray()['title'] === 'Buttermilk Pancakes'
            && $content->toArray()['subtitle'] === 'Step 2 timer')
        ->assertLiveActivityLinked('cook-timer-1', 'sunnyhome://recipes/1/cook');
});

it('starts no live activity where the phone does not support one', function () {
    Native::visit('/recipes/1/cook')->call('toggleTimer', 2)->assertSet('timerStep', 2);

    Native::fakeBridge()->assertNotCalled('LiveActivities.Start');
});

it('cancels the notification and ends the live activity when a timer is stopped', function () {
    $bridge = Native::fakeBridge()
        ->withLiveActivities(ActivityFixture::make('cook-timer-1', ActivityContent::make('Buttermilk Pancakes'), id: 'activity-1'))
        ->withLiveActivityEnded('activity-1');

    Native::visit('/recipes/1/cook')
        ->call('toggleTimer', 2)
        ->tap('cook-timer-stop');

    $bridge->assertCancelled('cook-timer-1')->assertLiveActivityEnded('activity-1');
});

it('leaves the alert to the notification once notifications are allowed', function () {
    $this->freezeSecond();

    $screen = Native::visit('/recipes/1/cook')
        ->call('showTab', 1)
        ->emitNative(PermissionGranted::class, ['granted' => true])
        ->assertSet('notifies', true)
        ->tap('cook-step-2-timer');

    $this->travel(300)->seconds();

    $screen->firePoll('tick')->assertSet('timerStep', null);

    Native::fakeBridge()->assertNotCalled('ToastKit.Show');
});
