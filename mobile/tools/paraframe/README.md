# Paraframe refresh

Keeps the "SunnyHome - mobile" Paraframe board in step with `main`. Every screen and state of the iOS app is on that board as a frame titled "…, as built" (recreated from a simulator screenshot) or "…, from code" (a state the simulator can't show, built from the views).

## What a run does

Run it when you want the board brought up to date: start **M: Refresh Paraframe** in Solo, or run `python3 refresh.py run` here. When `origin/main` has moved since the last run, it:

1. **Finds the affected frames.** It lists the changed files under `mobile/`, then the frames whose source files use them, following Blade includes, components, and the traits and support classes each screen uses.
   - Shared styling changed (tokens, `app/Ui`, icons, layouts, CSS, copy): every frame is affected.
   - NativePHP or its patches changed: every screen is captured and compared with last time, and only the ones that look different go on.
2. **Builds and captures.**
   - It works in this checkout, which must be on a clean `main`. It pulls it first.
   - It resets a local server's database with the Strawhats seed (`migrate:fresh --seed`) and serves it.
   - It builds the app onto a dedicated simulator, pointed at that server (it sets `SUNNY_API_URL` in `mobile/.env` for the build and puts it back after), and signs in as Nami.
   - It captures each affected frame. The steps to reach each one are in `manifest.json`: menu taps, searches, the empty team, alerts (always cancelled).
3. **Updates the frames.** It hands those frames, their new screenshots and the code diff to agents (Claude Code or Codex) with `brief.md`. The agents update the frames in place.
4. **Reports.** It posts a Trello card listing:
   - what changed
   - what couldn't be captured
   - anything new in the code that may need a frame of its own: routes, menus, alerts, sheets, empty states

## Setup (once per Mac)

- **A database for its server** (for example `web_paraframe`). It's created on the first run if missing.
- **A simulator of its own.** Nobody else should install builds on it. Set `root_ca` to lerd's mkcert root so the simulator can load photos from local S3.
- **`~/Library/Application Support/SunnyParaframe/config.json`**, which holds:
  - the board and the simulator id
  - the local server: its port and database
  - the seeded account to sign in with
  - `lookups`: the seeded records the manifest's placeholders stand for (`{thing}` is the item named "Log Pose"…)
  - `labels`: the seeded names it taps (`{home_team}`, `{feed_name}`…)
  - the Trello board, list and labels
  - the agent command: `claude` (with `--model` and `--allowedTools`) or `codex exec`
- **Trello credentials in the keychain:**
  - `security add-generic-password -s sunny-paraframe-trello -a key -w <api key>`
  - `security add-generic-password -s sunny-paraframe-trello -a token -w <token>`
- **`refresh.py baseline <sha>`:** the commit the board currently matches.

## By hand

- `refresh.py run`: one run, if `main` moved.
- `refresh.py run --since <sha> --no-agents --no-report`: build and capture only, to check the capture steps. It never moves the baseline.
- `refresh.py run --manifest some.json`: run just a few frames.
- `refresh.py run --all --since <sha>`: rebuild every frame.

Runs, logs, screenshots and reports go in `~/Library/Application Support/SunnyParaframe/runs/`.

## Adding a frame

When a run's report lists something new, design or capture its frame on the board. Then add it to `manifest.json`:

- the frame reference
- the title
- the route
- the steps to reach it: `tap`, `search` or `scroll_to`
- `dismiss`: `sheet`, `menu` or `alert`
- `team: "empty"`, for empty states
- `full_page`, for screens
- `code: true`, for states that can't be captured
