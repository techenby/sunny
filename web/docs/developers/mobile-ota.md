---
title: Mobile OTA Updates
description: How the mobile app receives over-the-air updates from Bifrost, and how to ship one.
order: 4
---

# Mobile OTA Updates

The mobile app (`mobile/`) has the [`nativephp/mobile-ota`](https://nativephp.com/plugins/nativephp/mobile-ota)
plugin installed. An OTA (over-the-air) update sends a new copy of the app's PHP code, views and assets straight to
installed phones, without a store review. Updates only come from
[Bifrost](https://bifrost.nativephp.com/docs/mobile/builds/ota), which signs them for this exact app.

OTA is installed but switched off: no release has been published, and the app never checks for one on its own.

## How it's set up

- `NativeServiceProvider::plugins()` registers `Nativephp\MobileOta\OtaServiceProvider`, so the plugin is compiled
  into native builds.
- `NATIVEPHP_OTA_MODE` is `manual` (the plugin's default, and set in `.env.example`). In `manual` mode the app only
  checks when code calls the `Ota` facade, and nothing in the app does yet.
- Bifrost writes the rest of the plugin's settings (`NATIVEPHP_OTA_PROJECT_UUID`, `NATIVEPHP_OTA_ENDPOINT`,
  `NATIVEPHP_OTA_TOKEN`, the shell fingerprint and `APP_ENV`) into each store build. Don't set them yourself.

## What an update can change

An update can replace routes, controllers, native components, Blade views, CSS, JavaScript and images.

It can't change anything compiled into the app. These need a new store build:

- adding, removing or updating a NativePHP plugin
- updating `nativephp/mobile` or `nativephp/mobile-ui`
- anything in `config/nativephp.php` (app ID, permissions, deep links, iPad support and so on)
- the patches in `mobile/patches/`, when they touch native code
- the OTA mode, for phones that haven't installed an update yet

Big redesigns, new features, or changes to what the store listing describes should go through review as a store build
even when they'd technically fit in an update.

## Arcs

Bifrost builds and OTA releases belong to an Arc: Staging or Production. A phone only receives updates published to
the Arc its store build was made on, and only for the same native setup (the shell fingerprint).

Our Bifrost team only has the Production Arc, so every build is Production. There's nowhere to try an update on
TestFlight first: an OTA release goes to everyone running a matching Production build.

## Shipping an update

1. Pick a mode before the store build that should receive updates. `manual` needs a "Check for updates" action in
   the app that calls `Ota::check()` and `Ota::downloadAndApply()`. `prompt` asks **Later** or **Update** once per
   launch. `silent` downloads in the background. Bifrost recommends `silent` on iOS, since prompting for code updates
   risks App Review guideline 2.5.2. Set `NATIVEPHP_OTA_MODE` under **Build Configuration** in Bifrost (or in
   `mobile/.env` for local builds), then ship a store build.
2. Wait for that build to pass review and reach phones. Builds without the plugin, or with a different mode, keep
   their behavior.
3. Merge the change to `main`. Check it doesn't touch anything in the "can't change" list above.
4. In Bifrost, click **Ship It!**, pick the mobile project and `main`, choose the **OTA** build type and the
   Production Arc. If the commit's native setup doesn't match a shipped build, Bifrost says what changed and you need
   a store build instead.
5. Phones pick the update up based on their mode and apply it the next time the app starts.

Each OTA build uses one Bifrost build credit. Don't publish an update while a store build is in review, since it can
change what the reviewer sees.

## Testing

The plugin adds helpers to NativePHP's fake bridge for Pest:

```php
Native::fakeBridge()->withOtaUpdate();

Native::test(Settings::class)
    ->tap('Check for updates')
    ->assertOtaChecked()
    ->assertOtaDownloaded()
    ->assertOtaQueued();
```

`mobile/tests/Feature/OtaUpdatesTest.php` covers the plugin's registration and the `manual` default.
