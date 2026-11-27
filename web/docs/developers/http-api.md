---
title: HTTP API
description: Authenticate with Sanctum and work with SunnyHome's recipe, inventory, list, routine, and sync endpoints.
order: 3
---

# HTTP API

SunnyHome exposes a Sanctum-protected JSON API under `/api`. It currently supports
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
`two_factor_enabled`, `current_team_id`, `onboarded_at`, `created_at`, and
`updated_at`, plus a plaintext `token` and its `expires_at` timestamp. Store
the token securely; it is not shown again. This endpoint allows 5 failed
attempts per minute for each email and IP address, then returns `429`; a
successful sign-in resets the count. Emails are matched case-insensitively, the
same as signing in on the web.

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

## Create an account

A client can create an account and get a token in one step:

```http
POST /api/register
Accept: application/json
Content-Type: application/json

{
  "name": "Nami",
  "email": "nami@example.com",
  "password": "your-password",
  "password_confirmation": "your-password",
  "device_name": "Nami's iPhone"
}
```

Accounts are created with the same rules as registering on the web: `name`,
`email` and a confirmed `password` are required, the email must not already
have an account, and it is stored in lowercase. The new user gets a personal
team, the same as on the web.

The response is `201` with the same body as
[the token endpoint](#create-a-token). `onboarded_at` is `null` for a new
account, so the client should show onboarding; someone who registers with a
pending team invitation skips onboarding, so theirs is already set. The token
lasts 30 days, like a token from signing in. The new user is sent a link to
verify their email, and needs to verify it before onboarding or syncing; see
[Email verification](#email-verification).

This endpoint allows 6 requests a minute for each IP address, then returns
`429`. It returns `404` when registration is turned off.

## Forgotten passwords

Send a password reset link:

```http
POST /api/forgot-password
Accept: application/json
Content-Type: application/json

{
  "email": "nami@example.com"
}
```

The email is the same one the web sends, and its link opens the web's reset
password page. The response is always `202` with the same `message`, whether
or not the email has an account, so it doesn't reveal who uses SunnyHome. A
missing or invalid `email` returns `422`.

Each account gets at most one link a minute; requests inside that minute still
return `202` but send nothing. This endpoint also allows 6 requests a minute
for each IP address, then returns `429`.

## Team behavior

Item, recipe, list, and routine endpoints are scoped to a team in the URL:
`/api/teams/{team}/...`, where `{team}` is the team's `slug` (returned by
`GET /api/sync`). The user must belong to that team.

The API never changes the user's current team in SunnyHome, so a client can work
with several teams at once and switching teams is purely a client-side choice.
A record requested under a team it doesn't belong to returns `404`.

`GET /api/sync` is different: it returns teams, recipes, items, lists, list
items, routines, routine steps, and upcoming routines across every team the
user belongs to.

## Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/user` | Return the authenticated user, wrapped in `data`, with the same fields as the token response. |
| `PATCH` | `/api/user` | Change the user's `name` and/or `email`. Returns the user. |
| `PUT` | `/api/user/password` | Change the password with `current_password`, `password` and `password_confirmation`. |
| `POST` | `/api/user/email/verification-notification` | Send the email verification link again. |
| `POST` | `/api/teams` | Create a team the user owns. |
| `PATCH` | `/api/teams/{team}` | Rename a team or change its timezone, week start or address. |
| `POST` | `/api/teams/{team}/leave` | Leave a team. |
| `PATCH` | `/api/teams/{team}/members/{user}` | Change a member's role. |
| `DELETE` | `/api/teams/{team}/members/{user}` | Remove a member. |
| `GET` | `/api/teams/{team}/invitations` | List the team's pending invitations. |
| `POST` | `/api/teams/{team}/invitations` | Invite someone by email. |
| `DELETE` | `/api/teams/{team}/invitations/{code}` | Cancel an invitation. |
| `GET` | `/api/invitations` | List the pending invitations sent to the user's email. |
| `POST` | `/api/invitations/{code}/accept` | Join the team and delete the invitation. |
| `POST` | `/api/invitations/{code}/decline` | Decline and delete the invitation. |
| `GET` | `/api/sync` | Synchronize all accessible teams, recipes, items, lists, and routines. |
| `POST` | `/api/register` | Create an account and get a token. |
| `POST` | `/api/forgot-password` | Email a password reset link. |
| `POST` | `/api/sanctum/token/two-factor` | Complete a two-factor challenge. |
| `POST` | `/api/sanctum/token/refresh` | Exchange the current token for a new one. |
| `POST` | `/api/logout` | Revoke the current token. |
| `GET` | `/api/teams/{team}/items` | List the team's inventory. |
| `POST` | `/api/teams/{team}/items` | Create an inventory entry. |
| `GET` | `/api/teams/{team}/items/{item}` | Read an inventory entry. |
| `PATCH` | `/api/teams/{team}/items/{item}` | Update an inventory entry. |
| `DELETE` | `/api/teams/{team}/items/{item}` | Delete an inventory entry. |
| `POST` | `/api/teams/{team}/items/{item}/restore` | Bring back a deleted inventory entry. |
| `DELETE` | `/api/teams/{team}/items/{item}/force` | Delete a deleted inventory entry forever. |
| `POST` | `/api/teams/{team}/items/{item}/duplicate` | Create 1–25 copies. |
| `POST` | `/api/teams/{team}/items/{item}/photos` | Add a photo to an inventory entry. |
| `PATCH` | `/api/teams/{team}/items/{item}/photos/{photo}` | Move a photo to another position. |
| `DELETE` | `/api/teams/{team}/items/{item}/photos/{photo}` | Remove a photo. |
| `GET` | `/api/teams/{team}/recipes` | List the team's recipes. |
| `POST` | `/api/teams/{team}/recipes` | Create a recipe. |
| `POST` | `/api/teams/{team}/recipes/import` | Create a recipe from a web page. |
| `GET` | `/api/teams/{team}/recipes/{recipe}` | Read a recipe. |
| `PATCH` | `/api/teams/{team}/recipes/{recipe}` | Update a recipe. |
| `DELETE` | `/api/teams/{team}/recipes/{recipe}` | Delete a recipe. |
| `POST` | `/api/teams/{team}/recipes/{recipe}/copy` | Copy a recipe to another of the user's teams. |
| `PUT` | `/api/teams/{team}/recipes/{recipe}/share` | Turn on a recipe's share link. |
| `DELETE` | `/api/teams/{team}/recipes/{recipe}/share` | Turn off a recipe's share link. |
| `GET` | `/api/shared-recipes/{token}` | Read a recipe someone shared. |
| `POST` | `/api/shared-recipes/{token}/save` | Save a shared recipe to one of the user's teams. |
| `GET` | `/api/teams/{team}/checklists` | List the team's lists with their items. |
| `POST` | `/api/teams/{team}/checklists` | Create a list. |
| `GET` | `/api/teams/{team}/checklists/{checklist}` | Read a list with its items. |
| `PATCH` | `/api/teams/{team}/checklists/{checklist}` | Update a list. |
| `DELETE` | `/api/teams/{team}/checklists/{checklist}` | Delete a list. |
| `POST` | `/api/teams/{team}/checklists/{checklist}/clear-completed` | Remove a list's checked items. |
| `POST` | `/api/teams/{team}/checklists/{checklist}/reset` | Uncheck every item on a list. |
| `GET` | `/api/teams/{team}/checklists/{checklist}/items` | List a list's items in order. |
| `POST` | `/api/teams/{team}/checklists/{checklist}/items` | Add an item to the end of a list. |
| `GET` | `/api/teams/{team}/checklists/{checklist}/items/{item}` | Read a list item. |
| `PATCH` | `/api/teams/{team}/checklists/{checklist}/items/{item}` | Rename, link, check, or uncheck a list item. |
| `DELETE` | `/api/teams/{team}/checklists/{checklist}/items/{item}` | Remove a list item. |
| `GET` | `/api/teams/{team}/routines` | List the team's routines and their steps. |
| `POST` | `/api/teams/{team}/routines` | Create a routine. |
| `GET` | `/api/teams/{team}/routines/{routine}` | Read a routine and its steps. |
| `PATCH` | `/api/teams/{team}/routines/{routine}` | Update a routine or reorder its steps. |
| `DELETE` | `/api/teams/{team}/routines/{routine}` | Delete a routine. |
| `POST` | `/api/teams/{team}/routines/{routine}/steps` | Add a step to the end of a routine. |
| `PATCH` | `/api/teams/{team}/routines/{routine}/steps/{step}` | Rename a routine step. |
| `DELETE` | `/api/teams/{team}/routines/{routine}/steps/{step}` | Remove a routine step. |
| `GET` | `/api/teams/{team}/routine-occurrences` | List the routines due on a day, with each step's progress. |
| `PATCH` | `/api/teams/{team}/routine-occurrences/{occurrence}/steps/{step}` | Complete or uncomplete a step. |

Successful creates return `201`; successful deletes return `204`.
Collections and single resources use Laravel's standard `data` wrapper.

## Account

Profile changes use the same rules as Settings on the web. Fields left out of
`PATCH /api/user` are unchanged. Changing the email clears `email_verified_at`
and sends a link to verify the new address.
`POST /api/user/email/verification-notification` returns `202` when it sends
the link and `204` when the email is already verified. Password changes return
`204`, and password changes and verification emails are limited to 6 requests
a minute.

## Email verification

An account needs a verified email before it can use its household, the same
as on the web. Until `email_verified_at` is set, every endpoint except signing
in, signing out, refreshing a token, and the `/api/user` account endpoints
returns `423` with `"email_verification_required": true`. Use `423` to show a
"verify your email" screen rather than treating it as a permission error, and
keep queued changes to send once the email is verified.

This includes answering team invitations, which are matched to the account by
its email address.

## Teams

Team endpoints follow the same rules and permissions as the web's team
settings, and they need a connection: don't queue them for later.

- Anyone can create a team (`name`). Creating or joining a team doesn't change
  the user's current team in SunnyHome.
- Owners and admins can update a team with any of `name`, `timezone`,
  `week_start` (0 is Sunday) and `address` (all of `address`, `city`, `state`,
  `zip`, `lat`, `long`). Renaming a team changes its `slug`.
- Admins and members can leave. Owners can't leave, and nobody can leave a
  personal team.
- Only the owner can change roles (`admin` or `member`) and remove members. The
  owner can't be changed or removed (`404`).
- Every member can list invitations. Owners and admins can invite (`email`,
  `role`), which emails a link, and cancel. Invitations expire after 3 days.
- Accepting or declining an invitation sent to another email address, or one
  that has expired, fails with a `422` on `invitation`.
- Accepting or declining deletes the invitation. Retrying is safe: accepting
  one that's gone returns the team if the user is now a member (and `404`
  otherwise), and declining one that's gone returns `204`.

Team responses are the team as in `GET /api/sync`, with its `members`.
Invitations have `code`, `email`, `role`, `expires_at` and `created_at`, and
`GET /api/invitations` adds `team` (`id`, `name`, `slug`) and `inviter`
(`id`, `name`). Deleting a team and transferring ownership are web only.

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

An entry can have up to 10 photos. Each entry in a response has `photos`, in
order, each with `id`, `position`, `url`, and `thumb_url`. The first photo is
the primary one, and `photo_url` and `thumb_url` on the entry are its URLs.
On create and update, `photo` and `remove_photo` act on the primary photo only:
`photo` replaces it and `remove_photo` deletes it, leaving any other photos in
place.

Send `photo_order`, a list of photo ids, on update to put an entry's photos in
that order. Ids that aren't the entry's photos are ignored, and photos left out
keep their order after the ones listed.

Add a photo with `photo` (an image up to 10 MB), plus an optional `position`
(0 makes it the primary photo; it goes last by default) and `client_uuid`.
Retrying with the same `client_uuid` returns the photo already saved. Move a
photo by sending its new `position`; the others shift to make room.

Duplicate an entry with an optional count:

```json
{
  "count": 3
}
```

Deleting an entry moves anything inside it to the top level. Deleted entries
still come back from `GET /api/sync` with `deleted_at` set, so a client can
show them in a trash view. Restoring one returns it with `200`. If the place it
was in is still deleted, it comes back at the top level. Restoring an entry
that isn't deleted changes nothing and also returns `200`, so retrying a
restore is safe.

The force endpoint only works on deleted entries; an entry that isn't deleted
returns `404`. It deletes the entry for good, along with its photos, and
returns `204`. It also returns `204` when the entry is already gone, so
retrying a permanent delete is safe.

## Recipe payloads

Only `name` is required. Supported optional fields are `source`, `servings`,
`prep_time`, `cook_time`, `total_time`, `description`, `ingredients`,
`instructions`, `notes`, `nutrition`, `tags`, `photo`, `client_uuid` (see
[Retrying creates](#retrying-creates)), and `parent_id` (a recipe in the same
team).

```json
{
  "name": "Weeknight Pasta",
  "servings": "4",
  "total_time": "30 minutes",
  "ingredients": "1 lb pasta\n2 cups sauce",
  "instructions": "Cook pasta. Warm sauce. Combine.",
  "tags": ["Dinner", "Vegetarian"]
}
```

`ingredients` and `instructions` take text with one entry per line, up to
20,000 characters; start a line with `# ` to make it a section heading.
`description` and `notes` allow 20,000 characters and `nutrition` 5,000.
`tags` is a list of up to 50 strings, and sending it on update replaces the
recipe's tags.

`photo` is an image up to 10 MB and 10,000 pixels on each side; send it as
multipart form data. On update, `photo` replaces the recipe's photo and
`remove_photo: true` deletes it. If both are sent, the new photo wins.

To import a recipe, send its page's `url` (and optionally a `client_uuid`) to
`POST /api/teams/{team}/recipes/import`. SunnyHome reads the page's Recipe
structured data and returns the new recipe with `201`. If the page loads but
has no recipe, it returns `422` on `url`. If SunnyHome can't open the page at
all (the site returns an error, blocks the request, or doesn't answer), it
returns `424` on `url`, so a client can tell the two apart. Imports are limited
to 30 a minute.

### Copying and sharing

Copy a recipe to another team the user belongs to by sending that team's
`team_id` (and optionally a `client_uuid`) to
`POST /api/teams/{team}/recipes/{recipe}/copy`. The copy keeps every field,
including tags and the photo, and its `parent_id` points at the original.
Copying to the recipe's own team returns `422` on `team_id`. It returns the
copy with `201`.

`PUT .../share` turns on a recipe's share link and `DELETE .../share` turns it
off. Both return the recipe, whose `share_token` and `share_url` are set while
sharing is on and `null` while it's off. Sending `PUT` again keeps the same
link, so both can safely be retried. Turning sharing off and on again makes a
new link, and the old one stops working.

Anyone signed in can read a shared recipe with
`GET /api/shared-recipes/{token}`, where `{token}` is its `share_token`. It
returns `id`, `team_id`, `name`, `source`, `servings`, `prep_time`,
`cook_time`, `total_time`, `description`, `ingredients`, `instructions`,
`notes`, `nutrition`, `share_token`, `share_url`, `photo_url`, and
`thumb_url`. A token that isn't shared any more returns `404`.

Save a shared recipe by sending a `team_id` the user belongs to (and optionally
a `client_uuid`) to `POST /api/shared-recipes/{token}/save`. The saved recipe
gets the shared recipe's text fields, with `source` set to the share URL; its
tags and photo aren't copied. It returns the new recipe with `201`. If that
team has already saved the recipe, or it's the team that shared it, it returns
the existing recipe with `200` instead.

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

Add an item with a `name`, and optionally a `url` (an `http` or `https` link,
such as a product page on a wish list) and `completed`. Send `url` on update to
change the link, or `null` to remove it. Check or uncheck an
existing item by sending `completed`:

```json
{
  "completed": true
}
```

SunnyHome records when the item was checked and by whom in `completed_at` and
`completed_by`. Checking an item that is already checked, or unchecking one
that isn't, leaves it unchanged, so a client can safely send the same change
again.

`clear-completed` permanently removes a list's checked items, and `reset`
unchecks every item so the list can be used again. Neither takes a body. Both
return the list with its remaining items, and both can safely be sent again.

## Retrying creates

Item, recipe, list, list item, routine, and routine step creates accept an
optional `client_uuid`. If
a request with the same `client_uuid` has already created a record, SunnyHome
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
SunnyHome clears whichever of the two the frequency doesn't use. `starts_on`
(`YYYY-MM-DD`) defaults to today in the team's timezone, and `is_active`
defaults to `true`; pausing a routine stops it appearing from today onward.
`user_id` is optional and must be a member of the team; leave it `null` for a
household routine.

Add a step with a `name`; it goes to the end of the routine. Removing a step
stops it from appearing on future days but keeps it on days already generated.

Send `step_order`, a list of step ids, on update to put a routine's steps in
that order. Ids that aren't the routine's steps are ignored, and steps left out
keep their order after the ones listed. Each step's `position` starts at `1`.

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

`teams` also ignores `since`, so member changes always arrive. Each team has
`members`, ordered by name, with each person's `id`, `name`, `initials` and
`role` (`owner`, `admin` or `member`). Email addresses aren't included.

## Errors

Expect standard Laravel JSON errors:

- `401` when the token is missing, invalid, or expired.
- `403` when the user doesn't belong to the team in the URL or isn't allowed to do something.
- `404` when the record doesn't exist or belongs to a different team.
- `422` when validation fails.
- `423` when the user's email needs verifying. See [Email verification](#email-verification).
- `424` when a recipe import can't open the page.
- `429` when too many token, two-factor, registration or password reset requests are made.

Sanctum tokens don't work with SunnyHome's [MCP server](/docs/developers/mcp/setup), which only accepts OAuth.
