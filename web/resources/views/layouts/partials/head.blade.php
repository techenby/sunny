<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@head

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
@stack('head')
