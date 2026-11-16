<?php

use App\Actions\Onboarding\SetUpHome;
use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('it shows on the dashboard for someone who just finished set-up', function () {
    $this->actingAs(User::factory()->gettingStarted()->create())
        ->get(route('dashboard'))
        ->assertSeeLivewire('dashboard.getting-started')
        ->assertSee('Get started with SunnyHome');
});

test('existing users never see it', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('dashboard.getting-started')
        ->assertDontSeeHtml('data-test="getting-started"');
});

test('it counts set-up as the first job done', function () {
    $user = User::factory()->gettingStarted()->create();
    Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage']);

    Livewire::actingAs($user)
        ->test('dashboard.getting-started')
        ->assertSee('Set up your home')
        ->assertSee('1 of 4 done')
        ->assertSeeHtml('data-test="job-inventory"');
});

test('it greets someone who started empty', function () {
    Livewire::actingAs(User::factory()->gettingStarted()->create())
        ->test('dashboard.getting-started')
        ->assertSee('Create your account')
        ->assertSee('Add your first place')
        ->assertSeeHtml('href="' . route('inventory.index') . '"');
});

test('it points at the first empty place', function () {
    $user = User::factory()->gettingStarted()->create();
    $garage = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage']);
    Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Shed']);

    Livewire::actingAs($user)
        ->test('dashboard.getting-started')
        ->assertSee('Put something in the Garage')
        ->assertSee('Add to Garage')
        ->assertSeeHtml('href="' . e(route('inventory.index', ['parentId' => $garage->id])) . '"');
});

test('putting something in a place ticks the inventory job', function () {
    $user = User::factory()->gettingStarted()->create();
    $garage = Item::factory()->for($user->currentTeam)->location()->create();
    Item::factory()->for($user->currentTeam)->bin()->childOf($garage)->create();

    Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Shed']);

    $jobs = Livewire::actingAs($user)->test('dashboard.getting-started')->get('jobs');

    expect(collect($jobs)->firstWhere('key', 'inventory'))
        ->done->toBeTrue()
        ->heading->toBe('Put something away');
});

test('the sample recipe does not tick the recipe job, but a recipe of their own does', function () {
    $user = User::factory()->gettingStarted()->create();
    Recipe::factory()->for($user->currentTeam)->create(['source' => SetUpHome::SAMPLE_RECIPE_URL]);

    $component = Livewire::actingAs($user)->test('dashboard.getting-started');

    expect(collect($component->get('jobs'))->firstWhere('key', 'recipe')['done'])->toBeFalse();

    Recipe::factory()->for($user->currentTeam)->create(['source' => null]);
    unset($component->instance()->jobs);

    expect(collect($component->instance()->jobs)->firstWhere('key', 'recipe')['done'])->toBeTrue();
});

test('a second member ticks the invite job', function () {
    $user = User::factory()->gettingStarted()->create();
    $user->currentTeam->memberships()->create(['user_id' => User::factory()->create()->id, 'role' => TeamRole::Member]);

    $jobs = Livewire::actingAs($user)->test('dashboard.getting-started')->get('jobs');

    expect(collect($jobs)->firstWhere('key', 'invite'))
        ->done->toBeTrue()
        ->href->toBe(route('teams.edit', $user->currentTeam->slug));
});

test('it hides itself once everything is done', function () {
    $user = User::factory()->gettingStarted()->create();
    $team = $user->currentTeam;
    $garage = Item::factory()->for($team)->location()->create();
    Item::factory()->for($team)->childOf($garage)->create();
    Recipe::factory()->for($team)->create();
    $team->memberships()->create(['user_id' => User::factory()->create()->id, 'role' => TeamRole::Member]);

    Livewire::actingAs($user)
        ->test('dashboard.getting-started')
        ->assertDontSeeHtml('data-test="getting-started"');
});

test('dismissing it hides it for good', function () {
    $user = User::factory()->gettingStarted()->create();

    Livewire::actingAs($user)
        ->test('dashboard.getting-started')
        ->assertSeeHtml('data-test="getting-started"')
        ->call('dismiss')
        ->assertDontSeeHtml('data-test="getting-started"');

    expect($user->fresh()->onboarding_dismissed_at)->not->toBeNull();

    Livewire::actingAs($user->fresh())
        ->test('dashboard.getting-started')
        ->assertDontSeeHtml('data-test="getting-started"');
});
