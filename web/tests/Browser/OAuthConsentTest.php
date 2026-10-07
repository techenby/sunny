<?php

use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        $this->artisan('passport:keys', ['--length' => 2048]);
    }
});

test('the consent screen disables both buttons once a choice is submitted', function () {
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->json('client_id');

    actingAs(User::factory()->create());

    $page = visit(route('passport.authorizations.authorize', [
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-123',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ], absolute: false));

    $page->assertNoJavaScriptErrors()
        ->assertSee('Connect Claude to Sunny')
        ->assertScript('document.querySelector("[data-test=approve-button]").disabled', false)
        ->script('
            const form = document.querySelector("[data-test=approve-button]").closest("form");
            form.addEventListener("submit", (event) => event.preventDefault());
            form.requestSubmit();
        ');

    $page->assertScript('document.querySelector("[data-test=approve-button]").disabled', true)
        ->assertScript('document.querySelector("[data-test=deny-button]").disabled', true);
});
