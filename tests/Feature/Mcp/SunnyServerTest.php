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
        'search-items',
        'get-item',
        'create-item',
        'update-item',
        'get-calendar-events',
        'list-calendar-feeds',
        'create-calendar-feed',
        'update-calendar-feed',
    )->toHaveCount(14);
});
