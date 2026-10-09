<x-layouts::guest :title="__('Privacy Policy')">
    <div class="max-w-3xl">
        <flux:heading level="1" size="xl">Privacy Policy</flux:heading>
        <flux:text class="mt-2">Last updated October 9, 2026</flux:text>

        <flux:text size="lg" class="mt-4">
            Sunny Home is a family dashboard operated by TechEnby ("we", "us"). This policy explains what
            information we collect when you use sunnyhome.app and the Sunny Home mobile apps, and what we do with it.
            Sunny Home is open source, so you can also read
            <flux:link href="https://github.com/techenby/sunny">the code</flux:link> to see exactly how your data is handled.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">What we collect</flux:heading>
        <ul class="mt-4 list-disc space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li><strong>Account information:</strong> your name, email address, and password (stored hashed), plus any passkeys or two-factor authentication you set up.</li>
            <li><strong>Household content:</strong> the recipes, photos, inventory, lists, routines, calendar feed URLs, and team details you and your household add.</li>
            <li><strong>Kiosk settings:</strong> the address you enter to show local weather, and the devices you pair as kiosks.</li>
            <li><strong>Connected assistants:</strong> access tokens for any AI assistant or API client you authorize.</li>
            <li><strong>Technical data:</strong> IP address, browser details, and logs that keep the service running and secure.</li>
        </ul>

        <flux:heading level="2" size="lg" class="mt-10">How we use it</flux:heading>
        <flux:text size="lg" class="mt-4">
            We use your information to run Sunny Home, keep your account secure, send account emails such as
            verification, password resets, and team invitations, and understand how the app is used so we can improve it.
            We do not sell your data and we do not show ads.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Who we share it with</flux:heading>
        <flux:text size="lg" class="mt-4">We share only what's needed with the services that help us run Sunny Home:</flux:text>
        <ul class="mt-4 list-disc space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li><strong>Laravel Cloud</strong> hosts the app and its database.</li>
            <li><strong>Laravel Nightwatch</strong> collects error and performance monitoring data.</li>
            <li><strong>Bento</strong> sends email and records page views on our website.</li>
            <li><strong>Mapbox</strong> suggests addresses when you set up a kiosk's weather location.</li>
            <li><strong>OpenWeather</strong> receives your kiosk's location to provide the forecast.</li>
        </ul>
        <flux:text size="lg" class="mt-4">
            When you add a calendar feed or import a recipe from a link, Sunny Home requests that URL on your behalf.
            When you connect an AI assistant, that assistant can read and change the household data you allow it to,
            under its own provider's privacy policy.
        </flux:text>
        <flux:text size="lg" class="mt-4">
            Everyone on a team can see the content shared with that team. We may also disclose information if required
            by law.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Cookies</flux:heading>
        <flux:text size="lg" class="mt-4">
            We use cookies to keep you signed in and protect against forged requests. Bento also uses a cookie to count
            page views on our website.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Keeping and deleting your data</flux:heading>
        <flux:text size="lg" class="mt-4">
            We keep your information for as long as your account is active. You can delete your account at any time from
            your profile settings, which removes your account information. Content you shared with a team may remain
            available to that team's other members.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Children</flux:heading>
        <flux:text size="lg" class="mt-4">
            Sunny Home accounts are meant for adults managing a household. We don't knowingly collect information from
            children under 13 without a parent's involvement.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Changes</flux:heading>
        <flux:text size="lg" class="mt-4">
            If we make material changes to this policy, we'll update the date above and, where appropriate, let you
            know by email.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Contact</flux:heading>
        <flux:text size="lg" class="mt-4">
            Questions about your privacy? Email us at
            <flux:link href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</flux:link>.
        </flux:text>
    </div>
</x-layouts::guest>
