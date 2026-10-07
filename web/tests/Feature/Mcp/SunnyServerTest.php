<?php

use App\Models\User;

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

test('the server exposes the expected tools', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertOk();

    $tools = collect($response->json('result.tools'))->pluck('name');

    expect($tools)->toContain(
        'search-recipes',
        'get-recipe',
        'create-recipe',
        'update-recipe',
        'delete-recipe',
        'import-recipe-from-url',
        'remix-recipe',
        'copy-recipe-to-team',
        'update-recipe-sharing',
        'search-items',
        'get-item',
        'create-item',
        'update-item',
        'delete-item',
        'restore-item',
        'duplicate-item',
        'move-item-to-team',
        'get-calendar-events',
        'list-calendar-feeds',
        'create-calendar-feed',
        'update-calendar-feed',
        'delete-calendar-feed',
        'list-checklists',
        'get-checklist',
        'create-checklist',
        'update-checklist',
        'delete-checklist',
        'add-checklist-items',
        'update-checklist-item',
        'remove-checklist-item',
        'clear-completed-checklist-items',
        'reset-checklist',
        'list-routines',
        'get-routine',
        'create-routine',
        'update-routine',
        'delete-routine',
        'add-routine-steps',
        'update-routine-step',
        'reorder-routine-steps',
        'remove-routine-step',
        'get-routine-board',
        'complete-routine-step',
        'list-teams',
        'switch-team',
        'update-team',
        'get-team-settings',
        'update-team-settings',
        'list-kiosk-devices',
        'forget-kiosk-device',
    )->toHaveCount(50);
});
