<x-layouts::guest>
    <div class="max-w-3xl">
        <flux:heading level="1" size="xl">Delete your SunnyHome account</flux:heading>
        <flux:text class="mt-2">Last updated November 16, 2026</flux:text>

        <flux:text size="lg" class="mt-4">
            SunnyHome is a family dashboard operated by TechEnby ("we", "us"). You can delete your SunnyHome account
            and its data at any time, from the SunnyHome mobile app or the website, without reinstalling the app.
            Deleting your account is permanent and can't be undone.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">In the mobile app</flux:heading>
        <ol class="mt-4 list-decimal space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li>Open SunnyHome and tap <strong>Preferences</strong> (the gear at the top of the Summary screen).</li>
            <li>Tap <strong>Delete account</strong>.</li>
            <li>Enter your password and tap <strong>Delete my account</strong>.</li>
        </ol>

        <flux:heading level="2" size="lg" class="mt-10">On the website</flux:heading>
        <ol class="mt-4 list-decimal space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li><flux:link :href="route('login')">Log in to sunnyhome.app</flux:link>.</li>
            <li>Go to <strong>Settings → <flux:link :href="route('profile.edit')">Profile</flux:link></strong>.</li>
            <li>Under <strong>Delete account</strong>, click <strong>Delete account</strong>, enter your password, and confirm.</li>
        </ol>

        <flux:heading level="2" size="lg" class="mt-10">If you can't log in</flux:heading>
        <flux:text size="lg" class="mt-4">
            Email us at
            <flux:link href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</flux:link>
            from the email address on your account and ask us to delete it. We may ask you to confirm the request
            before we delete anything.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">If you own a team other people use</flux:heading>
        <flux:text size="lg" class="mt-4">
            You can't delete your account while you own a team that other people also use, because deleting it would
            delete their recipes, lists, and everything else in it. SunnyHome tells you which teams are in the way. For
            each one, on the website:
        </flux:text>
        <ol class="mt-4 list-decimal space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li>Go to <strong>Settings → <flux:link :href="route('teams.index')">Teams</flux:link></strong> and choose the team.</li>
            <li>Open another member's role menu and choose <strong>Make owner</strong>, or remove the team's other members.</li>
        </ol>
        <flux:text size="lg" class="mt-4">
            Your personal team can't be handed to someone else, so remove its other members instead. Once no team you
            own is shared, you can delete your account from the app or the website. This works in a phone's browser too.
        </flux:text>

        <flux:heading level="2" size="lg" class="mt-10">What we delete</flux:heading>
        <flux:text size="lg" class="mt-4">When you delete your account, we immediately and permanently delete:</flux:text>
        <ul class="mt-4 list-disc space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li><strong>Your account:</strong> your name, email address, password, passkeys, and two-factor authentication settings.</li>
            <li><strong>Teams only you use:</strong> each team and everything in it, including recipes, inventory, photos, lists, routines, calendar feeds, kiosk devices, and invitations.</li>
            <li><strong>Your own lists and routines</strong> in teams you share with other people.</li>
            <li><strong>Access you've granted:</strong> API tokens, connected AI assistants, and the sessions that keep you signed in.</li>
        </ul>

        <flux:heading level="2" size="lg" class="mt-10">What we keep</flux:heading>
        <ul class="mt-4 list-disc space-y-2 ps-5 text-base text-zinc-500 dark:text-white/70 [&_strong]:font-medium [&_strong]:text-zinc-800 dark:[&_strong]:text-white">
            <li><strong>Shared team content:</strong> recipes, inventory, photos, lists, routines, and anything else you added to a team you share stays with that team, so its other members can keep using it.</li>
            <li><strong>Crash reports:</strong> if you chose to share crash reports from the mobile app, NativeWatch keeps them for up to 90 days. Email us to have them deleted sooner.</li>
            <li><strong>Email records:</strong> when you delete your account, we ask Bento, the service we use to send email, to delete your name and email address. Bento may keep some records it needs for legal or security reasons.</li>
        </ul>

        <flux:text size="lg" class="mt-10">
            Read our <flux:link :href="route('privacy')">Privacy Policy</flux:link> for more about the information we
            collect and how we use it. Questions? Email us at
            <flux:link href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</flux:link>.
        </flux:text>
    </div>
</x-layouts::guest>
