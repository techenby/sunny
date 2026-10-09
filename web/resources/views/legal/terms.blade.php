<x-layouts::guest :title="__('Terms of Service')">
    <div class="max-w-3xl">
        <flux:heading level="1" size="xl">Terms of Service</flux:heading>
        <flux:text class="mt-2">Last updated October 9, 2026</flux:text>

        <flux:text size="lg" class="mt-4">
            These terms apply to your use of Sunny Home, the hosted service at sunnyhome.app and its mobile apps,
            operated by TechEnby ("we", "us"). By creating an account or using Sunny Home, you agree to
            these terms.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Open source</flux:heading>
        <flux:text size="lg" class="mt-4">
            The Sunny Home source code is available on <flux:link href="https://github.com/techenby/sunny">GitHub</flux:link> under the
            <flux:link href="https://github.com/techenby/sunny/blob/main/LICENSE.md">MIT License</flux:link>. These terms cover the hosted
            service we run; the MIT License covers the code itself, including any copy you run yourself.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Your account</flux:heading>
        <flux:text size="lg" class="mt-4">
            You're responsible for keeping your login details secure and for activity on your account. Please give us
            accurate information when you sign up, and let us know if you think your account has been compromised.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Your content</flux:heading>
        <flux:text size="lg" class="mt-4">
            You own the recipes, photos, lists, and other content you add. You give us permission to store, process,
            and display that content only as needed to run Sunny Home for you and the teams you share it with. Only add
            content you have the right to share, such as recipes and photos you created or are allowed to use.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Acceptable use</flux:heading>
        <flux:text size="lg" class="mt-4">Please don't:</flux:text>
        <ul class="mt-4 list-disc space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li>break the law or infringe on anyone else's rights using Sunny Home;</li>
            <li>try to access accounts, teams, or data that aren't yours;</li>
            <li>disrupt the service, overload it with automated requests, or probe it for vulnerabilities without permission;</li>
            <li>use Sunny Home to send spam or distribute malware.</li>
        </ul>
        <flux:text size="lg" class="mt-4">If you find a security issue, please report it privately so we can fix it.</flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Third-party services</flux:heading>
        <flux:text size="lg" class="mt-4">
            Some features rely on other services, such as calendar providers, weather data, and AI assistants you
            choose to connect. Those services are governed by their own terms, and we aren't responsible for them.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Changes and availability</flux:heading>
        <flux:text size="lg" class="mt-4">
            Sunny Home is under active development. Features may change, and we may update these terms from time to
            time. If we make material changes, we'll update the date above and, where appropriate, let you know by
            email. We may suspend or close accounts that violate these terms.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Ending your account</flux:heading>
        <flux:text size="lg" class="mt-4">
            You can delete your account at any time from your profile settings. Content you shared with a team may
            remain available to that team's other members.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Disclaimers</flux:heading>
        <flux:text size="lg" class="mt-4">
            Sunny Home is provided "as is" and "as available", without warranties of any kind. Please keep your own
            copies of anything important. To the fullest extent allowed by law, TechEnby is not liable for
            any indirect, incidental, or consequential damages, or for any loss of data, arising from your use of Sunny
            Home.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">Contact</flux:heading>
        <flux:text size="lg" class="mt-4">
            Questions about these terms? Email us at
            <flux:link href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</flux:link>.
        </flux:text>
    </div>
</x-layouts::guest>
