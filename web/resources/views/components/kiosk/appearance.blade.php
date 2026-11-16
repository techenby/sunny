@props(['appearance'])

<style>
    :root.dark {
        color-scheme: dark;
    };
</style>
<script>
    window.Flux = {
        applyAppearance () {
            const appearance = @js($appearance->value);
            const dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

            document.documentElement.classList.toggle('dark', dark);
        },
    };

    window.Flux.applyAppearance();
</script>
