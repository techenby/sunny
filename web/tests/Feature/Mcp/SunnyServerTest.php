<?php

use App\Mcp\Servers\SunnyServer;
use App\Models\CalendarFeed;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Tools\ExecuteTools;
use Laravel\Mcp\Server\Tools\ToolSearch;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\Feature\Mcp\SunnyTestServer;

test('guests cannot access the mcp server', function () {
    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'ping',
    ])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

test('a bearer token authenticates against the mcp server', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
        ])
        ->assertOk();
});

function mcp(User $user, string $method, array $params = []): TestResponse
{
    return test()->withToken($user->createToken('Test')->plainTextToken)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => (object) $params,
        ]);
}

test('the server lists the everyday tools and the tool catalog', function () {
    $tools = collect(mcp(User::factory()->create(), 'tools/list')->assertOk()->json('result.tools'))->pluck('name');

    expect($tools->all())->toBe([
        'search-recipes',
        'get-recipe',
        'create-recipe',
        'import-recipe-from-url',
        'search-items',
        'get-item',
        'create-item',
        'update-item',
        'get-calendar-events',
        'list-checklists',
        'get-checklist',
        'create-checklist',
        'add-checklist-items',
        'update-checklist-item',
        'list-routines',
        'get-routine-board',
        'complete-routine-step',
        'list-teams',
        'switch-team',
        'search_tools',
        'execute_tools',
    ]);
});

test('the tool catalog holds every other tool', function () {
    $response = mcp(User::factory()->create(), 'tools/call', [
        'name' => 'search_tools',
        'arguments' => ['limit' => 50],
    ])->assertOk();

    $catalog = json_decode($response->json('result.content.0.text'), true);

    expect($catalog['hasMore'])->toBeFalse()
        ->and(collect($catalog['tools'])->pluck('name')->all())->toBe([
            'update-recipe',
            'delete-recipe',
            'remix-recipe',
            'copy-recipe-to-team',
            'update-recipe-sharing',
            'delete-item',
            'restore-item',
            'duplicate-item',
            'move-item-to-team',
            'list-calendar-feeds',
            'create-calendar-feed',
            'update-calendar-feed',
            'delete-calendar-feed',
            'update-checklist',
            'delete-checklist',
            'remove-checklist-item',
            'clear-completed-checklist-items',
            'reset-checklist',
            'get-routine',
            'create-routine',
            'update-routine',
            'delete-routine',
            'add-routine-steps',
            'update-routine-step',
            'reorder-routine-steps',
            'remove-routine-step',
            'update-team',
            'get-team-settings',
            'update-team-settings',
            'list-kiosk-devices',
            'forget-kiosk-device',
        ]);
});

test('search_tools finds catalog tools by keyword', function () {
    $response = mcp(User::factory()->create(), 'tools/call', [
        'name' => 'search_tools',
        'arguments' => ['query' => 'kiosk'],
    ])->assertOk();

    $names = collect(json_decode($response->json('result.content.0.text'), true)['tools'])->pluck('name');

    expect($names)->toContain('list-kiosk-devices', 'forget-kiosk-device');
});

test('execute_tools runs a catalog tool as the authenticated user', function () {
    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create(['name' => 'Crew Calendar']);
    CalendarFeed::factory()->create(['name' => 'Marine Calendar']);

    SunnyServer::actingAs($user)
        ->tool(new ExecuteTools(new ToolSearch([]), 1), [
            'calls' => [['name' => 'list-calendar-feeds', 'arguments' => []]],
        ])
        ->assertHasNoErrors()
        ->assertSee(['"ok":true', 'Crew Calendar'])
        ->assertDontSee('Marine Calendar');
});

test('catalog tools cannot be called directly', function () {
    $response = mcp(User::factory()->create(), 'tools/call', [
        'name' => 'list-calendar-feeds',
        'arguments' => (object) [],
    ]);

    $response->assertBadRequest();
    expect($response->json('error.message'))->toContain('list-calendar-feeds');
});

test('the test server registers every tool directly', function () {
    $server = new SunnyTestServer(new FakeTransporter);
    $tools = (fn () => $this->tools)->call($server);

    expect($tools)->toHaveCount(50)->each->toBeString();
});

test('the server lists its prompts', function () {
    $prompts = collect(mcp(User::factory()->create(), 'prompts/list')->assertOk()->json('result.prompts'))->pluck('name');

    expect($prompts->all())->toBe(['plan-meals', 'morning-check-in', 'find-item']);
});
