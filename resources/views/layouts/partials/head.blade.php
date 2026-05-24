<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name') : config('app.name') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

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
