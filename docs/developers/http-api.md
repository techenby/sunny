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

The response includes the user and a plaintext `token`. Store it securely; it
is not shown again.

Send the token with every protected request:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

> [!WARNING]
> A token acts as its owner and is not limited to one team. Revoke a token from
> Settings immediately if it is exposed or no longer used.

## Team behavior

Item and recipe endpoints operate on the authenticated user's current team.
The current team is stored on the user account, so switching teams in Sunny
also changes which records subsequent API calls list or create.

`GET /api/sync` is different: it returns teams, recipes, and items across every
team the user belongs to.

## Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/user` | Return the authenticated user. |
| `GET` | `/api/sync` | Synchronize all accessible teams, recipes, and items. |
| `GET` | `/api/items` | List current-team inventory. |
| `POST` | `/api/items` | Create an inventory entry. |
| `GET` | `/api/items/{item}` | Read an inventory entry. |
| `PATCH` | `/api/items/{item}` | Update an inventory entry. |
| `DELETE` | `/api/items/{item}` | Delete an inventory entry. |
| `POST` | `/api/items/{item}/duplicate` | Create 1–25 copies. |
| `GET` | `/api/recipes` | List current-team recipes. |
| `POST` | `/api/recipes` | Create a recipe. |
| `GET` | `/api/recipes/{recipe}` | Read a recipe. |
| `PATCH` | `/api/recipes/{recipe}` | Update a recipe. |
| `DELETE` | `/api/recipes/{recipe}` | Delete a recipe. |

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
uploaded image in `photo` are optional. Send image requests as multipart form
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
`instructions`, `notes`, `nutrition`, and `parent_id`.

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

- `401` when the token is missing or invalid.
- `403` when the user is not allowed to access the team-owned record.
- `404` when the record does not exist.
- `422` when validation fails.

The same Sanctum token can also authenticate Sunny's [MCP server](/docs/developers/mcp/setup).
