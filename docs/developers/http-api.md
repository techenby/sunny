---
title: HTTP API
description: Authenticate with Sanctum and work with Sunny's recipe, inventory, and sync endpoints.
order: 3
---

# HTTP API

Sunny exposes a Sanctum-protected JSON API under `/api`. It currently supports
inventory items, recipes, user details, and multi-team synchronization.

## Create a token

A signed-in user can create and revoke tokens from **Settings → API Tokens**.
A client can also exchange credentials for a token:

```http
POST /api/sanctum/token
Accept: application/json
Content-Type: application/json

{
  "email": "person@example.com",
  "password": "your-password",
  "device_name": "Kitchen tablet"
}
```

The response includes the user, a plaintext `token`, and its `expires_at`
timestamp. Store the token securely; it is not shown again. This endpoint allows
5 attempts per minute for each email and IP address, then returns `429`.

### Two-factor authentication

If the user has two-factor authentication enabled, the token endpoint returns a
challenge instead of a token:

```json
{
  "two_factor": true,
  "challenge": "CHALLENGE"
}
```

Exchange the challenge and a code from the user's authenticator app for a
token within 5 minutes:

```http
POST /api/sanctum/token/two-factor
Accept: application/json
Content-Type: application/json

{
  "challenge": "CHALLENGE",
  "code": "123456"
}
```

Send `recovery_code` instead of `code` to use a recovery code; it is consumed
and replaced. A successful response matches the token endpoint's. An invalid
code returns `422` and the challenge can be retried, up to 5 attempts per
minute. Each challenge can be completed only once.

### Token lifetime

Tokens issued this way expire after 30 days. Before a token expires, exchange it
for a fresh one:

```http
POST /api/sanctum/token/refresh
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

The response contains a new `token` and `expires_at`. The old token is revoked
immediately. An expired token cannot be refreshed; sign in again instead.

Tokens created in Settings use the expiration chosen there.

Send the token with every protected request:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

> [!WARNING]
> A token acts as its owner and is not limited to one team. Revoke a token from
> Settings immediately if it is exposed or no longer used.

## Team behavior

Item and recipe endpoints are scoped to a team in the URL:
`/api/teams/{team}/...`, where `{team}` is the team's `slug` (returned by
`GET /api/sync`). The user must belong to that team.

The API never changes the user's current team in Sunny, so a client can work
with several teams at once and switching teams is purely a client-side choice.
A record requested under a team it doesn't belong to returns `404`.

`GET /api/sync` is different: it returns teams, recipes, and items across every
team the user belongs to.

## Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/user` | Return the authenticated user. |
| `GET` | `/api/sync` | Synchronize all accessible teams, recipes, and items. |
| `POST` | `/api/sanctum/token/two-factor` | Complete a two-factor challenge. |
| `POST` | `/api/sanctum/token/refresh` | Exchange the current token for a new one. |
| `GET` | `/api/teams/{team}/items` | List the team's inventory. |
| `POST` | `/api/teams/{team}/items` | Create an inventory entry. |
| `GET` | `/api/teams/{team}/items/{item}` | Read an inventory entry. |
| `PATCH` | `/api/teams/{team}/items/{item}` | Update an inventory entry. |
| `DELETE` | `/api/teams/{team}/items/{item}` | Delete an inventory entry. |
| `POST` | `/api/teams/{team}/items/{item}/duplicate` | Create 1–25 copies. |
| `GET` | `/api/teams/{team}/recipes` | List the team's recipes. |
| `POST` | `/api/teams/{team}/recipes` | Create a recipe. |
| `GET` | `/api/teams/{team}/recipes/{recipe}` | Read a recipe. |
| `PATCH` | `/api/teams/{team}/recipes/{recipe}` | Update a recipe. |
| `DELETE` | `/api/teams/{team}/recipes/{recipe}` | Delete a recipe. |

Successful creates return `201`; successful deletes return `204`.
Collections and single resources use Laravel's standard `data` wrapper.

## Item payloads

Create an item with:

```json
{
  "name": "Extension Cord",
  "type": "item",
  "parent_id": 42,
  "metadata": {
    "length": "50 ft",
    "color": "orange"
  }
}
```

`type` must be `location`, `bin`, or `item`. `parent_id`, `metadata`, and an
uploaded image in `photo` are optional. `parent_id` must reference an item in
the same team. Send image requests as multipart form
data.

Duplicate an entry with an optional count:

```json
{
  "count": 3
}
```

## Recipe payloads

Only `name` is required. Supported optional fields are `source`, `servings`,
`prep_time`, `cook_time`, `total_time`, `description`, `ingredients`,
`instructions`, `notes`, `nutrition`, and `parent_id` (a recipe in the same
team).

```json
{
  "name": "Weeknight Pasta",
  "servings": "4",
  "total_time": "30 minutes",
  "ingredients": "1 lb pasta\n2 cups sauce",
  "instructions": "Cook pasta. Warm sauce. Combine."
}
```

## Incremental synchronization

Pass an ISO-8601 timestamp to return records updated since a previous sync:

```http
GET /api/sync?since=2026-08-16T12:00:00Z
```

The response contains `teams`, `recipes`, `items`, and a new `synced_at`
timestamp to use for the next request. Deleted records are included so an
offline client can remove local copies.

## Errors

Expect standard Laravel JSON errors:

- `401` when the token is missing, invalid, or expired.
- `403` when the user doesn't belong to the team in the URL.
- `404` when the record doesn't exist or belongs to a different team.
- `422` when validation fails.
- `429` when too many token or two-factor requests are made.

The same Sanctum token can also authenticate Sunny's [MCP server](/docs/developers/mcp/setup).
