<x-layouts::guest :title="__('Terms of Service')">
    <article class="prose dark:prose-invert max-w-none">
        <h1>Terms of Service</h1>
        <p><em>Last updated October 9, 2026</em></p>

        <p>
            These terms apply to your use of Sunny Home, the hosted service at sunnyhome.app and its mobile apps,
            operated by TechEnby ("we", "us"). By creating an account or using Sunny Home, you agree to
            these terms.
        </p>

        <h2>Open source</h2>
        <p>
            The Sunny Home source code is available on <a href="https://github.com/techenby/sunny">GitHub</a> under the
            <a href="https://github.com/techenby/sunny/blob/main/LICENSE.md">MIT License</a>. These terms cover the hosted
            service we run; the MIT License covers the code itself, including any copy you run yourself.
        </p>

        <h2>Your account</h2>
        <p>
            You're responsible for keeping your login details secure and for activity on your account. Please give us
            accurate information when you sign up, and let us know if you think your account has been compromised.
        </p>

        <h2>Your content</h2>
        <p>
            You own the recipes, photos, lists, and other content you add. You give us permission to store, process,
            and display that content only as needed to run Sunny Home for you and the teams you share it with. Only add
            content you have the right to share, such as recipes and photos you created or are allowed to use.
        </p>

        <h2>Acceptable use</h2>
        <p>Please don't:</p>
        <ul>
            <li>break the law or infringe on anyone else's rights using Sunny Home;</li>
            <li>try to access accounts, teams, or data that aren't yours;</li>
            <li>disrupt the service, overload it with automated requests, or probe it for vulnerabilities without permission;</li>
            <li>use Sunny Home to send spam or distribute malware.</li>
        </ul>
        <p>If you find a security issue, please report it privately so we can fix it.</p>

        <h2>Third-party services</h2>
        <p>
            Some features rely on other services, such as calendar providers, weather data, and AI assistants you
            choose to connect. Those services are governed by their own terms, and we aren't responsible for them.
        </p>

        <h2>Changes and availability</h2>
        <p>
            Sunny Home is under active development. Features may change, and we may update these terms from time to
            time. If we make material changes, we'll update the date above and, where appropriate, let you know by
            email. We may suspend or close accounts that violate these terms.
        </p>

        <h2>Ending your account</h2>
        <p>
            You can delete your account at any time from your profile settings. Content you shared with a team may
            remain available to that team's other members.
        </p>

        <h2>Disclaimers</h2>
        <p>
            Sunny Home is provided "as is" and "as available", without warranties of any kind. Please keep your own
            copies of anything important. To the fullest extent allowed by law, TechEnby is not liable for
            any indirect, incidental, or consequential damages, or for any loss of data, arising from your use of Sunny
            Home.
        </p>

        <h2>Contact</h2>
        <p>
            Questions about these terms? Email us at
            <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
        </p>
    </article>
</x-layouts::guest>
