---
title: MCP Setup
group: MCP
description: Connect MCP clients to Sunny's recipe, inventory, and calendar tools.
---

# MCP Setup

Sunny exposes a Laravel MCP server at `/mcp`. The server is registered in
`routes/ai.php` and uses `App\Mcp\Servers\SunnyServer`.

The server is protected by Sanctum bearer-token authentication and is rate
limited to 60 requests per minute:

```php
Mcp::web('/mcp', SunnyServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
```

## Create an API token

1. Sign in to Sunny.
2. Open **Settings**.
3. Open **API Tokens**.
4. Enter a token name, such as `Claude`, `Raycast`, or `MCP Inspector`.
5. Select **Create**.
6. Copy the token immediately. It is only shown once.

Treat the token like a password. Anyone with the token can access Sunny through
the MCP tools as your user and current team.

## Configure an MCP client

Use Sunny's MCP endpoint as the server URL:

```text
https://sunny.test/mcp
```

If your local Herd site uses a different host, keep the `/mcp` path and replace
the domain with the app's actual local URL.

Send the API token as a bearer token:

```http
Authorization: Bearer YOUR_API_TOKEN
```

The exact configuration shape depends on the MCP client. For clients that ask
for headers, add an `Authorization` header with the value above.

## Available tools

Sunny currently exposes tools for three areas:

- Recipes: search, read, create, update, delete, and import recipes from a URL.
- Inventory: search, read, create, and update items.
- Calendars: list feeds, create feeds, update feeds, and fetch upcoming events.

All tool access is scoped to the authenticated user's current team.

## Test with MCP Inspector

Laravel MCP includes an inspector command for debugging a registered server:

```bash
php artisan mcp:inspector /mcp
```

The inspector prints connection settings that can be copied into an MCP client.
Because Sunny's server uses Sanctum, include the bearer token header when testing
through the inspector or any other client.

Do not use `php artisan mcp:start` for this web server setup. That command starts
a local command-based MCP server and waits for protocol input.

## Verify from tests

The MCP server has feature coverage under `tests/Feature/Mcp`. To run the full
MCP test slice:

```bash
php artisan test --compact tests/Feature/Mcp
```

To verify only server authentication and tool registration:

```bash
php artisan test --compact tests/Feature/Mcp/SunnyServerTest.php
```

## Troubleshooting

If the client gets `401 Unauthorized`, create a new API token and confirm the
client is sending it as an `Authorization: Bearer ...` header.

If tools return empty or unexpected data, check which team is selected for the
user that owns the token. MCP tools use that user's current team.

If calendar event tools are slow, check the external calendar feed URLs. Events
are fetched live from ICS subscriptions.
