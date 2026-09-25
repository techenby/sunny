---
title: Teams and Households
description: Separate household data, invite people, and manage team roles.
order: 5
---

# Teams and Households

A team is a shared Sunny workspace. Recipes, inventory, routines, lists,
calendar feeds, and kiosk settings belong to a team so different households or
groups can keep their information separate.

Every account starts with a personal team. You can also create or join other
teams.

## Switch teams

Use the team switcher near the top of the main sidebar, then select the team
you want to use. Sunny updates the page to show that team's information.

Always check the current team before adding or editing shared information. API
and MCP actions also use the account's currently selected team unless their
documentation says otherwise.

## Create a team

You can create a team from the team switcher or from **Settings → Teams**:

1. Select **New team**.
2. Enter a team name.
3. Select **Create team**.

The creator becomes the team's owner and Sunny switches to the new team.

## Invite someone

1. Open **Settings → Teams**.
2. Open the team you manage.
3. Select **Invite member**.
4. Enter the person's email address and choose **Admin** or **Member**.
5. Select **Send invitation**.

Invitations expire after three days. The recipient must sign in with the same
email address that received the invitation. After acceptance, Sunny switches
their account to the joined team.

A pending invitation can be cancelled from the team's settings page.

## Understand roles

| Role | Team-management access |
| --- | --- |
| Owner | Full control, including member roles, member removal, and team deletion. |
| Admin | Can rename the team and create or cancel invitations. |
| Member | Can use the team's shared features but cannot manage team settings. |

Only the owner role is protected from reassignment or removal. Owners can
change another person's role or remove them from the team.

## Delete a team

Team owners can delete a non-personal team from its settings page. Deleting a
team removes access to its shared data and deletes its memberships. Anyone who
was actively using that team is switched to another available team, usually
their personal team.

> [!DANGER]
> Team deletion is destructive. Review the confirmation carefully before
> continuing.
