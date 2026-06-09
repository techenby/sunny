---
title: Calendar Feeds
group: Kiosk
description: Add Proton Calendar and Google Calendar events to your kiosk.
---

# Calendar Feeds

Calendar feeds let the kiosk display events from an external calendar. Each
feed needs an iCalendar (`.ics`) URL from your calendar provider.

Calendar feeds are read-only. Changes made in the kiosk do not update the
original calendar, and new or edited events may take a short time to appear.

> [!WARNING]
> Treat a calendar feed URL like a password. Anyone with the URL may be able to
> view your calendar. Do not paste it into email, chat, or other public places.

## Add a feed to the kiosk

1. Open **Kiosk** from the sidebar.
2. Select **Calendar** under **Preview**.
3. Select **Add Calendar Feed**.

![The Calendar feeds page with the Add Calendar Feed button highlighted.](/images/kiosk/calendar-feeds/kiosk-calendar-feeds.png)

Enter the following details:

- **Name**: A recognizable name, such as `Personal`, `Family`, or `Work`.
- **URL**: The iCalendar URL copied from Proton Calendar, Google Calendar, or
  another calendar provider.
- **Color**: The color used for this feed's events on the kiosk.

![The Add Feed form with fields for a name, URL, and color.](/images/kiosk/calendar-feeds/kiosk-add-feed.png)

Select **Create** to add the feed. Repeat these steps for each calendar you
want to display.

## Proton Calendar

1. Open [Proton Calendar](https://calendar.proton.me/).
2. Under **My calendars**, open the menu next to the calendar you want to add.
3. Select **Share**.

![The Share option in a Proton Calendar menu.](/images/kiosk/calendar-feeds/proton-share-menu.png)

4. Select **Share with anyone**.

![The Proton Calendar sharing dialog with Share with anyone selected.](/images/kiosk/calendar-feeds/proton-share-with-anyone.png)

5. Choose the access level for the feed. **Full view** includes event details;
   use a more limited option if the kiosk should only show availability.
6. Optionally enter a label so you can identify the link later.
7. Select **Create**.

![The Proton Calendar form for creating a public calendar link.](/images/kiosk/calendar-feeds/proton-create-public-link.png)

8. Select **Copy link**.

![The generated Proton Calendar link and Copy link button.](/images/kiosk/calendar-feeds/proton-copy-link.png)

Return to the kiosk's **Add Feed** form, paste the link into **URL**, choose a
name and color, and select **Create**.

![A Proton Calendar URL entered in the kiosk Add Feed form.](/images/kiosk/calendar-feeds/kiosk-add-proton-feed.png)

## Google Calendar

Google Calendar must be opened in a desktop browser to copy an iCal address.

1. Open [Google Calendar](https://calendar.google.com/).
2. Under **My calendars**, open the menu next to the calendar you want to add.
3. Select **Settings and sharing**.

![The Settings and sharing option in a Google Calendar menu.](/images/kiosk/calendar-feeds/google-settings-and-sharing.png)

4. Scroll to **Integrate calendar**.
5. Under **Secret address in iCal format**, select the copy button.

![The Google Calendar Integrate calendar section with the secret iCal address.](/images/kiosk/calendar-feeds/google-secret-ical-address.png)

> [!TIP]
> Use the **Secret address in iCal format** unless you intentionally made the
> calendar public. The public iCal address does not work for a private
> calendar.

Return to the kiosk's **Add Feed** form, paste the address into **URL**, choose
a name and color, and select **Create**.

## Remove access

Removing a feed from the kiosk stops displaying it, but does not invalidate
the feed URL. If a URL was exposed or should no longer work, revoke or reset it
in Proton Calendar or Google Calendar as well.
