---
title: MCP Setup
description: Connect MCP clients to Sunny with OAuth or an API token, and work on the MCP server locally.
order: 5
---

# MCP Setup

Sunny exposes a Laravel MCP server at `/mcp`. The server is registered in
`routes/ai.php` and uses `App\Mcp\Servers\SunnyServer`.

```php
Route::middleware('throttle:30,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp', SunnyServer::class)
    ->middleware(['auth:sanctum,api', 'throttle:60,1']);
```

## Authentication

The server accepts two kinds of bearer token:

| Client | How it authenticates | Guard |
| --- | --- | --- |
| Claude, ChatGPT, and other clients that support OAuth | OAuth 2.1 with PKCE. The user logs in to Sunny and approves access. | `api` (Passport) |
| Raycast, Claude Code with a header, scripts | A personal API token from **Settings → API tokens**. | `sanctum` |

The guard order matters. Passport clears the `Authorization` header when a
token isn't a JWT it issued, so `sanctum` must be tried first, or personal API
tokens stop working.

### OAuth

`Mcp::oauthRoutes()` registers the discovery and client registration
endpoints, and Passport provides the authorize and token endpoints:

| Endpoint | Purpose |
| --- | --- |
| `GET /.well-known/oauth-protected-resource` | Points clients at Sunny's authorization server. |
| `GET /.well-known/oauth-authorization-server` | Lists the authorize, token, and registration endpoints. |
| `POST /oauth/register` | Dynamic client registration. Clients register themselves as public clients with the `mcp:use` scope. |
| `GET /oauth/authorize` | Shows the consent screen (`pages::auth.oauth-authorize`) to a signed-in user. |
| `POST /oauth/token` | Exchanges the authorization code, or a refresh token, for tokens. |

An unauthenticated request to `/mcp` gets a `401` with a `WWW-Authenticate`
header that links to the protected resource metadata, which is how clients
discover the flow.

Access tokens last one day and refresh tokens 90 days, configured in
`AppServiceProvider::configurePassport()`. `passport:purge --hours=24` runs
daily to clear revoked and expired tokens.

`User` deliberately does not use Passport's `HasApiTokens` trait or implement
`OAuthenticatable`: their method signatures conflict with Sanctum's trait,
which the mobile app and API tokens rely on. Passport's guard only calls
`withAccessToken()` on the user, and Sanctum's version accepts Passport's
token. `tests/Feature/Mcp/OAuthTest.php` runs the whole flow, so a Passport
upgrade that changes this fails the tests.

Users can see and disconnect OAuth apps under **Connected apps** on the API
tokens settings page. Disconnecting revokes that app's access and refresh
tokens.

### API tokens

1. Sign in to Sunny.
2. Open **Settings → API tokens**.
3. Enter a token name, such as `Raycast` or `MCP Inspector`, and choose when it
   expires.
4. Select **Create**, then copy the token. It is only shown once.

Send it as a bearer token:

```http
Authorization: Bearer YOUR_API_TOKEN
```

Treat the token like a password. Anyone with it can use Sunny as you.

## Tools and prompts

Every tool acts as the authenticated user on their current team. Records from
other teams are never returned, and only the move and copy tools write to
another team, and only to one the user belongs to.

The everyday tools are listed directly. The rest are grouped in a `ToolSearch`
catalog, so clients see `search_tools` and `execute_tools` instead of every
definition:

| Listed directly | In the catalog |
| --- | --- |
| Recipes: search, get, create, import from a URL | Recipes: update, delete, remix, copy to a team, sharing |
| Inventory: search, get, create, update | Inventory: delete, restore, duplicate, move to a team |
| Calendar events | Calendar feeds: list, create, update, delete |
| Lists: list, get, create, add items, update an item | Lists: update, delete, remove an item, clear completed, reset |
| Routines: list, the routine board, complete a step | Routines: get, create, update, delete, and step editing |
| Teams: list, switch | Team name, team and kiosk settings, kiosk devices |

`switch-team` changes the user's current team everywhere, including the web
app.

The server also offers three prompts: `plan-meals`, `morning-check-in`, and
`find-item`. The `item` argument of `find-item` autocompletes from the team's
inventory.

To add a tool, create it under `app/Mcp/Tools`, then register it in
`SunnyServer::$tools`, either in the main list or in the `ToolSearch::class`
group. Return structured data with `Response::structured()` and declare an
`outputSchema()`.

## Local development

OAuth needs Passport's encryption keys. After `composer run setup`, generate
them once:

```bash
php artisan passport:keys
```

The keys are written to `storage/oauth-private.key` and
`storage/oauth-public.key`, which are not committed.

Hosted clients such as Claude on the web connect from their own servers, so
they cannot reach a local `.test` domain. Use Claude Code to try the OAuth flow
locally, because it runs the flow on your machine:

```bash
claude mcp add --transport http sunny https://sunny.test/mcp
```

Then run `/mcp` in Claude Code, choose **sunny**, and select **Authenticate**.
Your browser opens Sunny's consent screen. After you allow access, the app
appears under **Connected apps**.

To use an API token instead:

```bash
claude mcp add --transport http sunny https://sunny.test/mcp --header "Authorization: Bearer YOUR_API_TOKEN"
```

If your Herd site uses a different host, keep the `/mcp` path and change the
domain.

Laravel MCP also includes an inspector:

```bash
php artisan mcp:inspector /mcp
```

Do not use `php artisan mcp:start` for this server. It starts a command-based
MCP server and waits for protocol input.

## Deploying

When OAuth is first deployed:

1. Run the migrations, which add the `oauth_*` tables.
2. Generate keys with `php artisan passport:keys` and store them in the
   environment instead of key files:

```bash
PASSPORT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
...
-----END RSA PRIVATE KEY-----"
PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
...
-----END PUBLIC KEY-----"
```

Keep the same keys between deployments. Replacing them invalidates every
token that was already issued.

## Verify from tests

```bash
php artisan test --compact tests/Feature/Mcp
```

- `tests/Feature/Mcp/SunnyServerTest.php` checks authentication, the listed
  tools, the catalog, and the prompts.
- `tests/Feature/Mcp/OAuthTest.php` checks discovery, registration, consent,
  the code exchange, and an MCP call made with the OAuth token.
- Tests for catalog tools use `Tests\Feature\Mcp\SunnyTestServer`, which
  registers every tool directly so each one can be called by name.

## Troubleshooting

- **`401 Unauthorized`**: for an API token, confirm it hasn't expired and is
  sent as `Authorization: Bearer ...`. For OAuth, reconnect the app. If OAuth
  fails for everyone, check that the Passport keys are present and haven't
  changed.
- **Empty or unexpected data**: check the user's current team. Tools act on
  that team, and every account also has a personal team that is often empty.
- **A redirect to the login page instead of a `401`**: the request didn't ask
  for JSON. MCP clients send `Accept: application/json, text/event-stream`;
  add that header when testing with `curl`.
- **Slow calendar tools**: events are fetched live from each feed's ICS URL,
  so check the feeds.
