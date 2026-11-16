<?php

use App\Actions\Onboarding\SetUpHome;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get('/any-team/welcome')->assertRedirect(route('login'));
});

test('a new user sees home set-up', function () {
    $user = User::factory()->needsOnboarding()->create(['name' => 'Alex Rivera']);

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertSee('<title>Set up your home - ' . config('app.name') . '</title>', false)
        ->assertSee('What’s in your home, Alex?')
        ->assertSee("Alex Rivera's Team")
        ->assertSee(SetUpHome::PLACES);
});

test('someone who already finished onboarding goes to the dashboard instead', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::onboarding')
        ->assertRedirect(route('dashboard'));
});

test('the dashboard sends a new user to home set-up', function () {
    Livewire::actingAs(User::factory()->needsOnboarding()->create())
        ->test('pages::dashboard')
        ->assertRedirect(route('onboarding'));
});

test('it starts with a few places and starters picked', function () {
    Livewire::actingAs(User::factory()->needsOnboarding()->create())
        ->test('pages::onboarding')
        ->assertSet('places', ['Kitchen pantry', 'Garage', 'Hall closet'])
        ->assertSet('starters', ['groceries', 'bedtime'])
        ->assertSee('We’ll create 3 places, 1 list and 1 routine.');
});

test('the summary follows what is picked', function () {
    Livewire::actingAs(User::factory()->needsOnboarding()->create())
        ->test('pages::onboarding')
        ->set('places', ['Garage'])
        ->set('starters', ['bedtime', 'morning', 'recipe'])
        ->assertSee('We’ll create 1 place, 2 routines and 1 recipe.')
        ->set('places', [])
        ->set('starters', [])
        ->assertSee('Nothing picked yet.');
});

test('setting up creates what was picked and lands on the dashboard', function () {
    fakeSampleRecipe();
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)
        ->test('pages::onboarding')
        ->set('places', ['Garage', 'Shed'])
        ->set('starters', ['groceries', 'bedtime', 'recipe'])
        ->call('setUp')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->needsOnboarding())->toBeFalse()
        ->and($team->items()->pluck('name')->sort()->values()->all())->toBe(['Garage', 'Shed'])
        ->and($team->checklists()->pluck('name')->all())->toBe(['Groceries'])
        ->and($team->routines()->pluck('name')->all())->toBe(['Bedtime'])
        ->and($team->recipes()->value('source'))->toBe(SetUpHome::SAMPLE_RECIPE_URL);
});

test('starting empty creates nothing and lands on the dashboard', function () {
    Http::fake();
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)
        ->test('pages::onboarding')
        ->call('startEmpty')
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->needsOnboarding())->toBeFalse()
        ->and($team->items()->exists())->toBeFalse()
        ->and($team->checklists()->exists())->toBeFalse()
        ->and($team->routines()->exists())->toBeFalse()
        ->and($team->recipes()->exists())->toBeFalse();

    Http::assertNothingSent();
});

test('a place of their own is added and picked', function () {
    Livewire::actingAs(User::factory()->needsOnboarding()->create())
        ->test('pages::onboarding')
        ->set('newPlace', '  Under the stairs ')
        ->call('addPlace')
        ->assertHasNoErrors()
        ->assertSet('customPlaces', ['Under the stairs'])
        ->assertSet('places', ['Kitchen pantry', 'Garage', 'Hall closet', 'Under the stairs'])
        ->assertSet('newPlace', '')
        ->assertSeeHtml('value="Under the stairs"');
});

test('adding a place that is already offered picks it without repeating it', function () {
    Livewire::actingAs(User::factory()->needsOnboarding()->create())
        ->test('pages::onboarding')
        ->set('newPlace', 'Shed')
        ->call('addPlace')
        ->assertSet('customPlaces', [])
        ->assertSet('places', ['Kitchen pantry', 'Garage', 'Hall closet', 'Shed']);
});

test('a place needs a name', function () {
    Livewire::actingAs(User::factory()->needsOnboarding()->create())
        ->test('pages::onboarding')
        ->set('newPlace', '')
        ->call('addPlace')
        ->assertHasErrors(['newPlace' => 'required']);
});

test('only the offered starters can be set up', function () {
    $user = User::factory()->needsOnboarding()->create();

    Livewire::actingAs($user)
        ->test('pages::onboarding')
        ->set('starters', ['groceries', 'pirate ship'])
        ->call('setUp')
        ->assertHasErrors(['starters.1']);

    expect($user->fresh()->needsOnboarding())->toBeTrue()
        ->and($user->currentTeam->checklists()->exists())->toBeFalse();
});

test('setting up twice, like a double click, only creates the starters once', function () {
    Http::fake();
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)
        ->test('pages::onboarding')
        ->set('places', ['Garage', 'Shed'])
        ->set('starters', ['groceries', 'bedtime'])
        ->call('setUp')
        ->call('setUp')
        ->assertRedirect(route('dashboard'));

    expect($team->items()->count())->toBe(2)
        ->and($team->checklists()->count())->toBe(1)
        ->and($team->routines()->count())->toBe(1);
});

test('both buttons are disabled while either one is working', function () {
    Livewire::actingAs(User::factory()->needsOnboarding()->create())
        ->test('pages::onboarding')
        ->assertSeeHtml('wire:loading.attr="disabled"')
        ->assertSeeHtml('wire:target="setUp, startEmpty"');
});
