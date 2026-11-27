<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('shared recipe is viewable without authentication', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Test Recipe']);

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('Test Recipe');
});

test('shared recipe uses the recipe name as the page title', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Test Recipe']);

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('<title>Test Recipe - ' . config('app.name') . '</title>', false)
        ->assertSee('<meta property="og:description" content="A recipe shared with you on Sunny.', false);
});

test('shared recipes are hidden from search engines', function () {
    $recipe = Recipe::factory()->shared()->create();

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('shared recipe hides empty sections instead of offering to edit them', function () {
    $recipe = Recipe::factory()->shared()->create();

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertDontSee('Upload a photo')
        ->assertDontSee('No description yet')
        ->assertDontSee('No ingredients yet')
        ->assertDontSee('No instructions yet')
        ->assertDontSee('Add a description');
});

test('invalid share token returns 404', function () {
    $this->get(route('recipes.shared', 'nonexistent-token'))
        ->assertNotFound();
});

test('unshared recipe is not publicly accessible', function () {
    $recipe = Recipe::factory()->create();

    expect($recipe->share_token)->toBeNull();

    $this->get('/recipes/shared/null')
        ->assertNotFound();
});

test('authenticated user can add shared recipe to their team', function () {
    $recipe = Recipe::factory()->shared()->create([
        'name' => 'Grandma Cookies',
        'description' => 'Delicious cookies',
        'ingredients' => 'Flour',
        'instructions' => 'Mix',
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSee('Add to My Team');
});

test('guest does not see add to my team button', function () {
    $recipe = Recipe::factory()->shared()->create();

    $this->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertDontSee('Add to My Team');
});

test('adding shared recipe to team creates a copy', function () {
    $recipe = Recipe::factory()->shared()->create([
        'name' => 'Shared Pasta',
        'description' => 'A nice pasta recipe',
        'servings' => '4',
        'tags' => ['Dinner'],
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect();

    expect(Recipe::firstWhere('team_id', $user->currentTeam->id))->not->toBeNull()
        ->name->toBe('Shared Pasta')
        ->description->toBe('A nice pasta recipe')
        ->servings->toBe('4')
        ->tags->toBe(['Dinner'])
        ->source->toBe(route('recipes.shared', $recipe->share_token))
        ->share_token->toBeNull();
});

test('owner sees disabled add button', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->shared()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('recipes.shared', $recipe->share_token))
        ->assertOk()
        ->assertSeeHtml('wire:click="addToMyTeam" disabled');
});

test('previously added recipe shows view in my recipes', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Shared Pasta']);
    $user = User::factory()->create();

    $copy = Recipe::factory()->for($user->currentTeam)->create([
        'source' => route('recipes.shared', $recipe->share_token),
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->assertOk()
        ->assertSee('Add to My Team')
        ->call('addToMyTeam')
        ->assertRedirect(route('recipes.show', ['recipe' => $copy]));

    expect(Recipe::where('team_id', $user->currentTeam->id)->where('source', route('recipes.shared', $recipe->share_token))->count())->toBe(1);
});

test('adding recipe that already exists redirects to existing', function () {
    $recipe = Recipe::factory()->shared()->create(['name' => 'Shared Pasta']);
    $user = User::factory()->create();

    $existing = Recipe::factory()->for($user->currentTeam)->create([
        'source' => route('recipes.shared', $recipe->share_token),
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect(route('recipes.show', $existing));

    expect(Recipe::where('team_id', $user->currentTeam->id)->count())->toBe(1);
});

test('a shared page left open stops working when sharing is turned off or the link changes', function (string $change) {
    $recipe = Recipe::factory()->shared()->create();
    $page = Livewire::actingAs(User::factory()->create())->test('pages::recipes.shared', ['shareToken' => $recipe->share_token]);

    $change === 'off' ? $recipe->disableSharing() : $recipe->enableSharing();

    $page->call('addToMyTeam')->assertNotFound();

    expect(Recipe::count())->toBe(1);
})->with(['off', 'new link']);

test('a shared page left open keeps working while the link stays the same', function () {
    $recipe = Recipe::factory()->shared()->create();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect();

    expect($user->currentTeam->recipes()->count())->toBe(1);
});

test('a guest who taps add is sent to log in and comes back to the shared recipe', function () {
    $recipe = Recipe::factory()->shared()->create();
    $user = User::factory()->create();

    Livewire::test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam')
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('recipes.shared', $recipe->share_token));
});

test('a guest who taps add comes back to the shared recipe after the two factor challenge', function () {
    $recipe = Recipe::factory()->shared()->create();
    $user = User::factory()->withTwoFactor()->create();

    Livewire::test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('recipes.shared', $recipe->share_token));
});

test('a guest who taps add and then registers comes back to the shared recipe', function () {
    $recipe = Recipe::factory()->shared()->create();

    Livewire::test('pages::recipes.shared', ['shareToken' => $recipe->share_token])
        ->call('addToMyTeam');

    $this->post(route('register.store'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('recipes.shared', $recipe->share_token));
});
