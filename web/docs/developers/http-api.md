---
title: HTTP API
description: Authenticate with Sanctum and work with Sunny's recipe, inventory, routine, and sync endpoints.
order: 3
---

# HTTP API

Sunny exposes a Sanctum-protected JSON API under `/api`. It currently supports
inventory items, recipes, routines, user details, and multi-team synchronization.

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

The response contains the user's `id`, `name`, `email`, `email_verified_at`,
`two_factor_enabled`, `current_team_id`, `created_at`, and `updated_at`, plus a
plaintext `token` and its `expires_at` timestamp. Store the token securely; it
is not shown again. This endpoint allows
5 failed attempts per minute for each email and IP address, then returns
`429`; a successful sign-in resets the count. Emails are matched
case-insensitively, the same as signing in on the web.

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
minute for each user across all challenges and IP addresses. Each challenge can
be completed only once; if two requests for the same challenge arrive at the
same time, the second returns `422` on `challenge`. If two-factor authentication is turned off before the
challenge is completed, the challenge is rejected; sign in again instead.

### Token lifetime

Tokens issued this way expire after 30 days. Before a token expires, exchange it
for a fresh one:

```http
POST /api/sanctum/token/refresh
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

The response contains a new `token` and `expires_at`. The old token is revoked
immediately. The new token keeps the original token's lifetime, so a Settings
token that never expires stays that way. An expired token cannot be refreshed;
sign in again instead.

Tokens created in Settings use the expiration chosen there. Tokens created
before expiration was introduced were given a 30-day expiration when it was
rolled out.

### Sign out

Revoke the token used for the request when the user signs out:

```http
POST /api/logout
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

The response is `204`. Other tokens for the same user are unaffected.

Send the token with every protected request:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

> [!WARNING]
> A token acts as its owner and is not limited to one team. Revoke a token from
> Settings immediately if it is exposed or no longer used.

## Team behavior

Item, recipe, and routine endpoints are scoped to a team in the URL:
`/api/teams/{team}/...`, where `{team}` is the team's `slug` (returned by
`GET /api/sync`). The user must belong to that team.

The API never changes the user's current team in Sunny, so a client can work
with several teams at once and switching teams is purely a client-side choice.
A record requested under a team it doesn't belong to returns `404`.

`GET /api/sync` is different: it returns teams, recipes, items, routines, and
upcoming routine occurrences across every team the user belongs to.

## Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/user` | Return the authenticated user, wrapped in `data`, with the same fields as the token response. |
| `GET` | `/api/sync` | Synchronize all accessible teams, recipes, items, routines, and upcoming occurrences. |
| `POST` | `/api/sanctum/token/two-factor` | Complete a two-factor challenge. |
| `POST` | `/api/sanctum/token/refresh` | Exchange the current token for a new one. |
| `POST` | `/api/logout` | Revoke the current token. |
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
| `GET` | `/api/teams/{team}/routines` | List the team's routines and their steps. |
| `POST` | `/api/teams/{team}/routines` | Create a routine and its steps. |
| `GET` | `/api/teams/{team}/routines/{routine}` | Read a routine and its steps. |
| `PATCH` | `/api/teams/{team}/routines/{routine}` | Update a routine and optionally its steps. |
| `DELETE` | `/api/teams/{team}/routines/{routine}` | Delete a routine. |
| `GET` | `/api/teams/{team}/routine-occurrences` | List the routines due on a day, with each step's progress. |
| `PATCH` | `/api/teams/{team}/routine-occurrences/{occurrence}/steps/{step}` | Complete or uncomplete a step. |

Successful creates return `201`; successful deletes return `204`. Creates that
send a `client_uuid` return `200` with the existing record when that
`client_uuid` was already used in the team.
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

## Routine payloads

Create a routine with `POST /api/teams/{team}/routines`:

```json
{
  "name": "Bedtime",
  "time_of_day": "evening",
  "frequency": "weekly",
  "weekdays": [0, 1, 2, 3, 4],
  "user_id": null,
  "starts_on": "2026-10-01",
  "is_active": true,
  "client_uuid": "9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d",
  "steps": [
    { "name": "Pajamas" },
    { "name": "Brush teeth" }
  ]
}
```

- `name` is required, up to 255 characters.
- `time_of_day` and `frequency` are required. `frequency` is `daily`, `weekly`,
  or `monthly`.
- `weekdays` (integers `0`-`6`, Sunday is `0`) is required for `weekly`, and
  `day_of_month` (`1`-`31`) is required for `monthly`. Fields the chosen
  frequency doesn't use are stored as `null`.
- `user_id` assigns the routine to a member of the team; `null` (the default)
  makes it a household routine.
- `starts_on` defaults to today in the team's timezone and `is_active` defaults
  to `true`.
- `client_uuid` is optional. Retrying a create with the same `client_uuid`
  returns the existing routine with `200` instead of creating a duplicate.

`PATCH /api/teams/{team}/routines/{routine}` accepts the same fields except
`client_uuid`, and every field is optional. Fields that are left out keep their
current value, including `frequency` when validating and normalising
`weekdays` and `day_of_month`: sending only `{"weekdays": [1, 3]}` for a weekly
routine works, while switching a routine to `weekly` or `monthly` requires the
matching `weekdays` or `day_of_month`.

`steps` is the routine's complete ordered list of steps, on create and update.
Each entry is an object with a required `name` and, on update, an optional
`id`:

- An entry with an `id` renames that existing step of the routine and moves it
  to its position in the list. An `id` that isn't an existing step of the same
  routine returns `422`, and `id` isn't accepted on create.
- An entry without an `id` creates a new step.
- Existing steps that aren't listed are deleted. Occurrences that already
  include a deleted step keep it, so past days still show what was asked.

Leave `steps` out of an update to leave the steps alone. Each step has `id`,
`name`, and `position` (starting at `1`).

`DELETE /api/teams/{team}/routines/{routine}` deletes a routine and returns
`204`. Deleted routines no longer generate occurrences.

Routine responses include `id`, `team_id`, `user_id`, `name`, `time_of_day`,
`frequency`, `weekdays`, `day_of_month`, `starts_on`, `is_active`,
`client_uuid`, `schedule_summary`, `user` (`id` and `name`, or `null`),
`steps`, and the `created_at`, `updated_at`, and `deleted_at` timestamps.
Changing a step also updates its routine's `updated_at`, so incremental
synchronization picks the routine up.

Each day a routine is due gets an *occurrence* with a row for each of the
routine's steps, and those rows are what get completed.

`GET /api/teams/{team}/routine-occurrences` returns the routines due today in
the team's timezone, ordered by time of day and name. Pass `date` as
`YYYY-MM-DD` for another day. Each occurrence includes its `routine` (with
`user` set to the assignee's `id` and `name`, or `null` for a household
routine) and its `steps`, each with `name`, `position`, `completed_at`, and
`completed_by`.

Complete a step with:

```json
{
  "completed": true
}
```

Send `false` to uncomplete it. The request sets the step's state rather than
toggling it, so retrying it is safe: completing a step that's already complete
keeps the original `completed_at` and `completed_by`.

## Incremental synchronization

Pass an ISO-8601 timestamp to return records updated since a previous sync:

```http
GET /api/sync?since=2026-08-16T12:00:00Z
```

The response contains `teams`, `recipes`, `items`, `routines` (with their
steps and assignee), `routine_occurrences`, and a new `synced_at` timestamp to
use for the next request. Deleted records are included so an offline client can
remove local copies.

`routine_occurrences` ignores `since`: it always contains every routine due
today and tomorrow in each team's timezone, shaped like the
`routine-occurrences` endpoint's response.

## Errors

Expect standard Laravel JSON errors:

- `401` when the token is missing, invalid, or expired.
- `403` when the user doesn't belong to the team in the URL.
- `404` when the record doesn't exist or belongs to a different team.
- `422` when validation fails.
- `429` when too many token or two-factor requests are made.

The same Sanctum token can also authenticate Sunny's [MCP server](/docs/developers/mcp/setup).
