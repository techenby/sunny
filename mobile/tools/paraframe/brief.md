# Updating SunnyHome's "as built" Paraframe frames

The Paraframe board "SunnyHome - mobile" has one frame for each screen and state of the SunnyHome iOS app: screens, sheets, menus, alerts, and empty, error and offline states. Each frame is hand-written HTML that recreates exactly what the app draws.

`main` has just changed, and the frames in your `frames.json` may no longer match it. For each one:

1. **Compare it.** Read the frame (`get_frame_html`, `get_frame_screenshot`).
   - For an "as built" frame, compare it with its new simulator screenshots.
   - For a "from code" frame, compare it with the code as it now is.
2. **Update it if it differs.** If anything differs (layout, components, copy, sizes, colours, what's shown), update the frame **in place** with `update_frame`.
   - Don't change its title, x or y. Do change its height if the page's height changed.
   - Add a one-line `summary` of what changed.
3. **Leave it if it already matches.** Many frames will, because the change may only have touched code they share.

This is a fidelity recreation, not design. Never improve anything, and never use Paraframe's designer (`design_frame`, `message_frame`).

## What you're given

- **`frames.json`:** your frames. Each entry has:
  - `frame`: the Paraframe reference
  - `title`
  - `kind`: "as built" or "from code"
  - `route`
  - `source_files`
  - `screenshots`: the new screenshots, for "as built" frames
  - `previous_screenshots`: what the screen looked like when the frame was last made, if there are any
- **Screenshots:**
  - Each is 1206×2622 px, which is 402×874 pt at 3x. Divide by 3 for CSS px.
  - With several screenshots, each is a 400pt upward scroll of the one before. Once scrolled, the large title collapses into the top bar.
  - Shrink a copy with `sips -Z 800 in.png --out <your folder>/x.png` if that helps you look.
- **The code changes since the frames were last updated:** a diff file, named in your prompt. Use it to see *what* changed, then check the screenshots for *how* it looks.
- **The app code** at the path in your prompt:
  - Views are in `resources/views/native/`, and components are in `resources/views/components/ui/`.
  - Screen classes are in `app/NativeComponents/`.
  - Tokens are in `config/native-ui.php`, and the card surface is in `app/Ui/Card.php`.
  - Where the code and a screenshot disagree, the screenshot wins.

## How a frame is written

- The HTML is one `<style>` block, then the body markup.
  - No `<html>`, `<head>`, `<body>` or `<script>`, and no relative URLs.
  - Images are base64 `data:` URLs, cropped from a screenshot with `sips`.
- The root `.screen` is 402px wide.
  - **Screens** show the page scrolled to the top at its full height. Fixed bars (pinned buttons, bottom bars, the tab bar) sit at the bottom of the frame, and the tab bar is left out where the app has none.
  - **Sheets, menus, dropdowns and alerts** are one 874px viewport: the screen behind as it sits, dimmed by iOS where the app dims it, with the overlay on top.
- **Shared pieces:** fonts (Nunito, from the existing frames' `@font-face` rules), the status bar, glass, cards and the tab bar. Copy them from the frame you're updating; don't redraw them.
- **Icons** are inline SVGs drawn to look like the SF Symbols in the screenshot.
- Write each new HTML file in your folder and pass it as `html_file`.
- Check every frame you change with `get_frame_screenshot` against its screenshot, and fix it until you'd have to look twice to tell them apart.

## Data

The app runs against a local server seeded with demo data (the Strawhat Pirates: Nami signed in, the crew, Baratie, her empty personal team). It has no personal data, so copy what the screenshots show exactly.

Frames made earlier may still show older demo data ("Family", "Nami Swan", made-up events). When you update a frame, replace that with the seeded data from the screenshots. For "from code" frames, use the same seeded names and records.

## Rules

- Touch only the frames in your `frames.json`.
- Don't touch the simulator, git, or any repo files. Read the repo only.

## Report

Write `REPORT.md` in your folder with one line per frame, starting `- `:

- the title
- "updated" or "already matched"
- what changed, in a few words
- anything you guessed or couldn't match
