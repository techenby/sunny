<x-layouts::guest :title="__('Privacy Policy')">
    <article class="prose dark:prose-invert max-w-none">
        <h1>Privacy Policy</h1>
        <p><em>Last updated October 9, 2026</em></p>

        <p>
            Sunny Home is a family dashboard operated by TechEnby ("we", "us"). This policy explains what
            information we collect when you use sunnyhome.app and the Sunny Home mobile apps, and what we do with it.
            Sunny Home is open source, so you can also read
            <a href="https://github.com/techenby/sunny">the code</a> to see exactly how your data is handled.
        </p>

        <h2>What we collect</h2>
        <ul>
            <li><strong>Account information:</strong> your name, email address, and password (stored hashed), plus any passkeys or two-factor authentication you set up.</li>
            <li><strong>Household content:</strong> the recipes, photos, inventory, lists, routines, calendar feed URLs, and team details you and your household add.</li>
            <li><strong>Kiosk settings:</strong> the address you enter to show local weather, and the devices you pair as kiosks.</li>
            <li><strong>Connected assistants:</strong> access tokens for any AI assistant or API client you authorize.</li>
            <li><strong>Technical data:</strong> IP address, browser details, and logs that keep the service running and secure.</li>
        </ul>

        <h2>How we use it</h2>
        <p>
            We use your information to run Sunny Home, keep your account secure, send account emails such as
            verification, password resets, and team invitations, and understand how the app is used so we can improve it.
            We do not sell your data and we do not show ads.
        </p>

        <h2>Who we share it with</h2>
        <p>We share only what's needed with the services that help us run Sunny Home:</p>
        <ul>
            <li><strong>Laravel Cloud</strong> hosts the app and its database.</li>
            <li><strong>Laravel Nightwatch</strong> collects error and performance monitoring data.</li>
            <li><strong>Bento</strong> sends email and records page views on our website.</li>
            <li><strong>Mapbox</strong> suggests addresses when you set up a kiosk's weather location.</li>
            <li><strong>OpenWeather</strong> receives your kiosk's location to provide the forecast.</li>
        </ul>
        <p>
            When you add a calendar feed or import a recipe from a link, Sunny Home requests that URL on your behalf.
            When you connect an AI assistant, that assistant can read and change the household data you allow it to,
            under its own provider's privacy policy.
        </p>
        <p>
            Everyone on a team can see the content shared with that team. We may also disclose information if required
            by law.
        </p>

        <h2>Cookies</h2>
        <p>
            We use cookies to keep you signed in and protect against forged requests. Bento also uses a cookie to count
            page views on our website.
        </p>

        <h2>Keeping and deleting your data</h2>
        <p>
            We keep your information for as long as your account is active. You can delete your account at any time from
            your profile settings, which removes your account information. Content you shared with a team may remain
            available to that team's other members.
        </p>

        <h2>Children</h2>
        <p>
            Sunny Home accounts are meant for adults managing a household. We don't knowingly collect information from
            children under 13 without a parent's involvement.
        </p>

        <h2>Changes</h2>
        <p>
            If we make material changes to this policy, we'll update the date above and, where appropriate, let you
            know by email.
        </p>

        <h2>Contact</h2>
        <p>
            Questions about your privacy? Email us at
            <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
        </p>
    </article>
</x-layouts::guest>
