---
title: Host Your Own SunnyHome
description: Run your own copy of SunnyHome on Laravel Cloud instead of using sunnyhome.app.
order: 7
---

# Host Your Own SunnyHome

SunnyHome is open source, so you don't have to keep your household on
sunnyhome.app. This guide walks through running your own copy on
[Laravel Cloud](https://cloud.laravel.com), the same host sunnyhome.app uses.

You'll need:

- A GitHub account to fork the repository.
- A Laravel Cloud account.
- A [Flux Pro](https://fluxui.dev) license. SunnyHome's interface is built
  with Flux Pro, and the build can't install it without a license.
- A domain name, if you don't want to use the address Cloud gives you.
- An email provider that supports SMTP, such as Resend, Postmark, or Amazon
  SES, so invitations and password resets reach people.

If you want to run SunnyHome on your own computer to change the code, see
[Local Development](/docs/developers/local-development) instead.

## Fork the repository

Fork [techenby/sunny](https://github.com/techenby/sunny) on GitHub. Cloud
deploys from your fork, so you choose when to take new changes.

The repository holds two apps. The web app, which is everything this guide
sets up, lives in the `web` folder.

## Create the application

1. In Laravel Cloud, create an application from your fork's repository.
2. When Cloud asks which directory holds the app, choose `web`.
3. Pick a region close to your household.

Cloud creates a `production` environment that deploys from your default
branch. Turn on push to deploy if you want every push to that branch to go
live.

## Add a database

SunnyHome runs on PostgreSQL. On the environment's canvas, select **Add
database** and create a **Laravel Serverless Postgres** database.

Serverless Postgres can scale to zero, so you only pay for the time it's
awake. sunnyhome.app uses a 60-second sleep timer. See [keep costs
down](#keep-costs-down) for how a kiosk affects this.

## Add file storage

Photos of recipes and inventory items are stored as files, and Cloud's servers
don't keep files between deployments. Select **Add bucket**, create a
**Laravel Object Storage** bucket, and make it the default disk.

Make the bucket **private**. SunnyHome shows photos through links that
expire after 30 minutes, so the files never need to be public.

## Set environment variables

Open the environment's settings and add these variables. Variables you add
take priority over the ones Cloud adds for you.

| Variable | Value |
| --- | --- |
| `APP_NAME` | `SunnyHome`, or whatever you'd like the app to be called. |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Your site's full address, such as `https://home.example.com`. |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `PASSPORT_PRIVATE_KEY` | See [create the AI assistant keys](#create-the-ai-assistant-keys). |
| `PASSPORT_PUBLIC_KEY` | See [create the AI assistant keys](#create-the-ai-assistant-keys). |
| `FLUX_USERNAME` | The email address on your Flux license. |
| `FLUX_LICENSE_KEY` | Your Flux license key. |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | Your email provider's SMTP settings. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | The address and name emails come from. |

If the environment doesn't already have an `APP_KEY`, create one by running
`php artisan key:generate --show` in a local copy of SunnyHome and add the
result as `APP_KEY`. Keep it the same from then on: changing it signs everyone
out and breaks two-factor authentication.

`SESSION_DRIVER` must be `database`. With cookie sessions, the page where you
allow an AI assistant to connect stores more than a browser cookie can hold,
and selecting **Allow access** fails.

Cloud has a built-in email resource, but it sends with Resend's PHP library,
which SunnyHome doesn't include. Use your provider's SMTP settings instead.
Resend also offers SMTP.

### Create the AI assistant keys

The [AI assistant connection](/docs/users/ai-assistants) signs its tokens with
a pair of keys. Without them, every assistant request fails.

In a local copy of SunnyHome, run:

```bash
php artisan passport:keys
```

This writes `storage/oauth-private.key` and `storage/oauth-public.key`. Copy
the full contents of each file, including the first and last lines, into
`PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`. Keep the private
key secret, and don't commit either file.

## Set the build and deploy commands

Open the environment's **Deployments** settings.

Cloud writes your variables to a `.env` file during the build but doesn't
make them available to the build's shell, so the build reads the Flux
license from that file before installing anything. Replace the build
commands with:

```bash
export $(grep -E '^FLUX_(USERNAME|LICENSE_KEY)=' .env | xargs)
composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"
composer install --no-dev
npm ci --audit false
npm run build
```

Set the deploy command to:

```bash
php artisan migrate --force
```

## Turn on the scheduler and a queue worker

Select the App cluster on the canvas, then:

1. Turn on **Scheduler**. Each night it prepares the next week of routines,
   clears expired sign-in tokens, and removes expired team invitations.
2. Under **Background processes**, add a queue worker with one process.
   Team invitation emails are sent from the queue, so they won't go out
   without it.

## Deploy

Deploy the environment. When it finishes, open your site and select
**Register** to create your account. Everyone in your household registers on
your site the same way, and you can invite them to your team from
[Teams and Households](/docs/users/teams).

To use your own domain, add it under the environment's **Network settings**,
then update `APP_URL` to match and deploy again.

## Optional services

These features turn on when their keys are set. Everything else works
without them.

| Variable | Used for |
| --- | --- |
| `OPENWEATHER_API_KEY` | The weather tile on the kiosk, from [OpenWeather](https://openweathermap.org/api). |
| `MAPBOX_API_KEY` | Address suggestions when you set the kiosk's location, from [Mapbox](https://www.mapbox.com). |

sunnyhome.app also uses Bento for email and page views. Leave the `BENTO_*`
variables unset; SunnyHome skips Bento when they're empty.

## Keep costs down

Cloud charges for the time your app and database are awake. Turn on **Scale
to zero** for the App cluster and keep the database's sleep timer short so
both sleep when nobody is using SunnyHome. Queued emails wait until the app
next wakes.

A kiosk display keeps things awake. It refreshes its data every ten minutes,
which wakes the app and the database each time. On sunnyhome.app, the kiosk
is the biggest part of the bill. See [what sunnyhome.app
costs](https://sunnyhome.app/costs) for real numbers.

## Connect AI assistants and the mobile app

AI assistants connect to your site the same way they connect to
sunnyhome.app. Follow [Connect an AI Assistant](/docs/users/ai-assistants)
and use the MCP server address shown on your site's **Settings → API tokens**
page.

The SunnyHome mobile app can't connect to your site. The copy we publish only
talks to sunnyhome.app, and there's no setting to change that. To use the
mobile app with your own server, you'd need to build it yourself from the
`mobile` folder with `SUNNY_API_URL` set to your site's address followed by
`/api`.

## Keep your copy up to date

When SunnyHome changes, sync your fork with the original repository on
GitHub. If push to deploy is on, Cloud deploys the update and runs any new
database migrations.

A few things are specific to sunnyhome.app and stay that way in your copy:

- The **Admin** page only appears for the maintainer's account. To use it,
  change the email address in the `admin` gate in
  `app/Providers/AppServiceProvider.php`.
- The **What it costs** page shows sunnyhome.app's costs, not yours.
