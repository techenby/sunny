<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@head

@fonts

@production
@if (filled(config('bentonow.site_uuid')))
<script src="https://app.bentonow.com/{{ config('bentonow.site_uuid') }}.js" defer></script>
<script>
  window.addEventListener('bento:ready', function () {
    @if (auth()->user() && auth()->user()->email)
      bento.identify(@js(auth()->user()->email));
    @endif
    bento.view();
  });
</script>
@endif
@endproduction

@vite(['resources/css/app.css', 'resources/js/app.js'])
@isset($kioskAppearance)
    <x-kiosk.appearance :appearance="$kioskAppearance" />
@else
    @fluxAppearance
@endisset
@stack('head')
