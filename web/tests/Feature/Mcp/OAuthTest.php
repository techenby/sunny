<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        $this->artisan('passport:keys', ['--length' => 2048]);
    }
});

function registerClient(): string
{
    return test()->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])
        ->assertCreated()
        ->json('client_id');
}

/** @return array{0: string, 1: string} */
function pkcePair(): array
{
    $verifier = Str::random(64);

    return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}

/** @return array<string, string> */
function authorizeQuery(string $clientId, string $challenge): array
{
    return [
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-123',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];
}

test('the server advertises its oauth metadata', function () {
    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonPath('authorization_servers.0', url('/'))
        ->assertJsonPath('scopes_supported', ['mcp:use']);

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'))
        ->assertJsonPath('registration_endpoint', url('/oauth/register'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

test('an mcp client can connect through the full oauth flow', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);
    Recipe::factory()->create(['name' => 'Secret Family Recipe']);

    $clientId = registerClient();
    [$verifier, $challenge] = pkcePair();

    $consent = $this->actingAs($user)
        ->get(route('passport.authorizations.authorize', authorizeQuery($clientId, $challenge)))
        ->assertOk()
        ->assertSee('Connect Claude to Sunny')
        ->assertSee($user->email);

    $redirect = $this->post(route('passport.authorizations.approve'), [
        'state' => '',
        'client_id' => $clientId,
        'auth_token' => $consent->viewData('authToken'),
    ])->assertRedirect();

    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $callback);

    expect($callback['state'])->toBe('state-123')
        ->and($callback)->toHaveKey('code');

    $tokens = $this->postJson(route('passport.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])
        ->assertOk()
        ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);

    auth()->forgetGuards();

    $this->flushSession()
        ->withToken($tokens->json('access_token'))
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'search-recipes', 'arguments' => (object) []],
        ])
        ->assertOk()
        ->assertSee('Chocolate Cake')
        ->assertDontSee('Secret Family Recipe');
});

test('denying the consent screen returns an access denied error to the client', function () {
    $user = User::factory()->create();
    $clientId = registerClient();
    [, $challenge] = pkcePair();

    $consent = $this->actingAs($user)
        ->get(route('passport.authorizations.authorize', authorizeQuery($clientId, $challenge)))
        ->assertOk();

    $redirect = $this->delete(route('passport.authorizations.deny'), [
        'state' => '',
        'client_id' => $clientId,
        'auth_token' => $consent->viewData('authToken'),
    ])->assertRedirect();

    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $callback);

    expect($callback['error'])->toBe('access_denied');
});

test('guests are sent to log in before the consent screen', function () {
    $clientId = registerClient();
    [, $challenge] = pkcePair();

    $this->get(route('passport.authorizations.authorize', authorizeQuery($clientId, $challenge)))
        ->assertRedirect(route('login'));
});

test('an invalid bearer token is rejected', function () {
    $this->withToken('not-a-real-token')
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});
