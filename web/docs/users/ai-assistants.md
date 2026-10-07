---
title: Connect an AI Assistant
description: Let Claude, ChatGPT, or another AI assistant use Sunny for you.
order: 6
---

# Connect an AI Assistant

You can connect an AI assistant, such as Claude or ChatGPT, to Sunny. Once it's
connected, you can ask it things like "What's on today's routine board?",
"Add milk and eggs to the shopping list", or "Where are the camping lanterns?",
and it uses Sunny to answer.

An assistant acts as you. It can see and change your recipes, inventory,
calendars, lists, routines, and team settings, but only on teams you belong
to. It can't change your password, two-factor authentication, or account.

## Connect an assistant

Sunny works with Claude, ChatGPT, Raycast, Claude Code, and other apps that
can sign in to an MCP server with OAuth.

1. In Sunny, open **Settings → API tokens** and copy the MCP server address
   under **Connecting to the MCP server**.
2. In your assistant, add a custom connector or MCP server and paste that
   address.
3. When the assistant asks you to sign in, log in to Sunny.
4. Review what the app will be able to do, then select **Allow access**.

The app then appears under **Connected apps** on the API tokens page.

API tokens on the same page are for developers using Sunny's HTTP API. They
can't be used to connect an AI assistant.

## Choose which team it works on

An assistant works on your current team, the same one the team switcher shows.
Every account also has a personal team, which is often empty, so if the
assistant can't find your household's information, ask it which team it's
using.

You can ask the assistant to switch teams. Switching also changes your current
team in Sunny's web app.

## Shortcuts

Some assistants show these as commands you can pick:

- **plan-meals**: plans dinners around your calendar using your recipes, then
  adds the ingredients to a shopping list once you approve the plan.
- **morning-check-in**: summarizes today's routines, calendar events, and open
  to-dos.
- **find-item**: finds where something is stored in your inventory.

## Disconnect an assistant

Open **Settings → API tokens** and select **Disconnect** next to the app under
**Connected apps**. The app loses access right away. You can connect it again
later.
