<p align="center">
    <img src="web/public/icon.svg" alt="" width="80" height="80">
</p>

<h1 align="center">Sunny Home</h1>

<p align="center">
    Your household, organized.<br>
    <a href="https://sunnyhome.app">sunnyhome.app</a>
</p>

Sunny Home keeps a family's recipes, lists, routines, calendars, and everything in storage in one shared place. You can use it on the web, on your phone, and on a kiosk display on the wall.

## Features

- **Recipes:** import recipes from any website, remix family favorites, and share a link with anyone.
- **Lists:** to-do, shopping, and wish lists for the whole household or just for you.
- **Inventory:** map the garage, basement, and pantry into locations and bins, with printable QR labels.
- **Routines:** morning routines and chore charts that reset daily, on chosen weekdays, or monthly.
- **Calendars:** subscribe to Google, Proton, or any iCal feed and see everyone's plans side by side.
- **Kiosk:** pair a tablet or wall display to show the calendar, routines, lists, and weather.
- **Mobile app (beta):** works offline and syncs when you're back online.
- **AI assistant:** a built-in MCP server lets Claude and other assistants work with your household.

## Repository layout

| Directory | What's inside |
| --- | --- |
| [`web/`](web) | The Laravel app behind sunnyhome.app, including the kiosk, API, and MCP server. |
| [`mobile/`](mobile) | The NativePHP mobile app. |

## Running it locally

The web app is a Laravel 13 application using PHP 8.4, Livewire 4, Flux UI, and Node 22. It uses SQLite locally, so no database server is needed.

```bash
cd web
composer run setup
composer run dev
```

The UI uses [Flux Pro](https://fluxui.dev), so you'll need a Flux license to install the Composer dependencies.

See the [web README](web/README.md) and the [developer guides](web/docs/developers/_index.md) for more commands, optional services, and the database diagram.

## Documentation

User and developer guides live in [`web/docs`](web/docs/index.md) and are published at [sunnyhome.app/docs](https://sunnyhome.app/docs).

## Contributing

Found a bug or have an idea? [Open an issue](https://github.com/techenby/sunny/issues).

## License

Sunny Home is open source under the [MIT License](LICENSE.md).
