<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name') : config('app.name') }}
</title>

<link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
<link rel="shortcut icon" href="/favicons/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="Sunny" />
<link rel="manifest" href="/favicons/site.webmanifest" />

@fonts

@production
<script src="https://app.bentonow.com/d14d22f048103e883848523b335aa614.js" defer></script>
<script>
  window.addEventListener('bento:ready', function () {
    @if (auth()->user() && auth()->user()->email)
      bento.identify({{ json_encode(auth()->user()->email) }}));
    @endif
    bento.view();
  });
</script>
@endproduction

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
