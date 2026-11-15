@props(['content'])

<div class="prose dark:prose-invert prose-headings:mt-4 prose-headings:mb-1 prose-headings:text-sm prose-headings:font-semibold prose-headings:text-zinc-500 dark:prose-headings:text-white/70 prose-p:text-zinc-500 dark:prose-p:text-white/70 prose-strong:text-zinc-800 dark:prose-strong:text-white prose-a:text-zinc-800 dark:prose-a:text-white prose-a:decoration-zinc-800/20 dark:prose-a:decoration-white/20 prose-li:text-zinc-500 dark:prose-li:text-white/70 prose-ol:my-0 prose-ul:my-0 prose-li:my-0 [--tw-prose-bullets:color-mix(in_oklab,var(--color-accent-content)_70%,transparent)] [--tw-prose-invert-bullets:color-mix(in_oklab,var(--color-accent-content)_70%,transparent)]">
    {!! $content !!}
</div>
