@props(['width', 'height', 'interactive' => false])

@once
    <script>
        function fitWelcomeScreen(screen) {
            const content = screen.firstElementChild

            new ResizeObserver(() => {
                content.style.transform = `scale(${screen.clientWidth / content.offsetWidth})`
            }).observe(screen)
        }
    </script>
@endonce

<div {{ $attributes->class('relative overflow-hidden') }} style="aspect-ratio: {{ $width }} / {{ $height }}" @unless ($interactive) aria-hidden="true" inert @endunless>
    <div class="absolute top-0 left-0 origin-top-left" style="width: {{ $width }}px; height: {{ $height }}px">
        {{ $slot }}
    </div>
</div>

<script>fitWelcomeScreen(document.currentScript.previousElementSibling)</script>
