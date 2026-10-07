---
title: HTTP API
description: Authenticate with Sanctum and work with Sunny's recipe, inventory, list, routine, and sync endpoints.
order: 3
---

# HTTP API

Sunny exposes a Sanctum-protected JSON API under `/api`. It currently supports
inventory items, recipes, lists, routines, user details, and multi-team synchronization.

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

Item, recipe, list, and routine endpoints are scoped to a team in the URL:
`/api/teams/{team}/...`, where `{team}` is the team's `slug` (returned by
`GET /api/sync`). The user must belong to that team.

The API never changes the user's current team in Sunny, so a client can work
with several teams at once and switching teams is purely a client-side choice.
A record requested under a team it doesn't belong to returns `404`.

`GET /api/sync` is different: it returns teams, recipes, items, lists, list
items, routines, routine steps, and upcoming routines across every team the
user belongs to.

## Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/user` | Return the authenticated user, wrapped in `data`, with the same fields as the token response. |
| `GET` | `/api/sync` | Synchronize all accessible teams, recipes, items, lists, and routines. |
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
| `GET` | `/api/teams/{team}/checklists` | List the team's lists with their items. |
| `POST` | `/api/teams/{team}/checklists` | Create a list. |
| `GET` | `/api/teams/{team}/checklists/{checklist}` | Read a list with its items. |
| `PATCH` | `/api/teams/{team}/checklists/{checklist}` | Update a list. |
| `DELETE` | `/api/teams/{team}/checklists/{checklist}` | Delete a list. |
| `GET` | `/api/teams/{team}/checklists/{checklist}/items` | List a list's items in order. |
| `POST` | `/api/teams/{team}/checklists/{checklist}/items` | Add an item to the end of a list. |
| `GET` | `/api/teams/{team}/checklists/{checklist}/items/{item}` | Read a list item. |
| `PATCH` | `/api/teams/{team}/checklists/{checklist}/items/{item}` | Rename, check, or uncheck a list item. |
| `DELETE` | `/api/teams/{team}/checklists/{checklist}/items/{item}` | Remove a list item. |
| `GET` | `/api/teams/{team}/routines` | List the team's routines and their steps. |
| `POST` | `/api/teams/{team}/routines` | Create a routine. |
| `GET` | `/api/teams/{team}/routines/{routine}` | Read a routine and its steps. |
| `PATCH` | `/api/teams/{team}/routines/{routine}` | Update a routine. |
| `DELETE` | `/api/teams/{team}/routines/{routine}` | Delete a routine. |
| `POST` | `/api/teams/{team}/routines/{routine}/steps` | Add a step to the end of a routine. |
| `PATCH` | `/api/teams/{team}/routines/{routine}/steps/{step}` | Rename a routine step. |
| `DELETE` | `/api/teams/{team}/routines/{routine}/steps/{step}` | Remove a routine step. |
| `GET` | `/api/teams/{team}/routine-occurrences` | List the routines due on a day, with each step's progress. |
| `PATCH` | `/api/teams/{team}/routine-occurrences/{occurrence}/steps/{step}` | Complete or uncomplete a step. |

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

## List payloads

Lists are called checklists in the API. Create one with:

```json
{
  "name": "Groceries",
  "type": "shopping",
  "user_id": 7
}
```

`type` must be `todo`, `shopping`, or `wishlist`. `user_id` is optional and
must be a member of the team; leave it `null` for a list that belongs to the
whole household.

Add an item with a `name`, and optionally `completed`. Check or uncheck an
existing item by sending `completed`:

```json
{
  "completed": true
}
```

Sunny records when the item was checked and by whom in `completed_at` and
`completed_by`. Checking an item that is already checked, or unchecking one
that isn't, leaves it unchanged, so a client can safely send the same change
again.

## Retrying creates

Item, recipe, list, list item, routine, and routine step creates accept an
optional `client_uuid`. If
a request with the same `client_uuid` has already created a record, Sunny
returns that record with `200` instead of creating another, so an offline
client can retry a create without making duplicates.

## Routine payloads

Create a routine with:

```json
{
  "name": "Bedtime",
  "time_of_day": "evening",
  "frequency": "weekly",
  "weekdays": [1, 3, 5],
  "user_id": 7
}
```

`time_of_day` must be `morning`, `afternoon`, `evening`, or `anytime`, and
`frequency` must be `daily`, `weekly`, or `monthly`. A weekly routine needs
`weekdays`, from `0` (Sunday) to `6` (Saturday); a monthly routine needs a
`day_of_month` from `1` to `31`, and runs on the last day of shorter months.
Sunny clears whichever of the two the frequency doesn't use. `starts_on`
(`YYYY-MM-DD`) defaults to today in the team's timezone, and `is_active`
defaults to `true`; pausing a routine stops it appearing from today onward.
`user_id` is optional and must be a member of the team; leave it `null` for a
household routine.

Add a step with a `name`; it goes to the end of the routine. Removing a step
stops it from appearing on future days but keeps it on days already generated.

Each day a routine is due gets an
*occurrence* with a row for each of the routine's steps, and those rows are
what get completed.

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

The response contains `teams`, `recipes`, `items`, `checklists`,
`checklist_items`, `routines`, `routine_steps`, `routine_occurrences`, and a
new `synced_at` timestamp to use for the next request. Deleted records are
included so an offline client can remove local copies. List items are removed
permanently rather than soft-deleted, and list items and routine steps are left
out once their list or routine is deleted, so compare a full sync against local
copies to find removed ones.

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

Sanctum tokens don't work with Sunny's [MCP server](/docs/developers/mcp/setup), which only accepts OAuth.
