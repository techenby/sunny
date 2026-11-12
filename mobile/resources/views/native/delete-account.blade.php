@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Delete account" back />

<scroll-view ref="delete-account-screen" fill class="bg-theme-background">
    <column class="w-full gap-6 px-6 py-6">
        <column class="w-full gap-3">
            <text ref="delete-account-warning" class="text-base text-theme-on-background">
                This permanently deletes your Sunny account. It can’t be undone.
            </text>
            <text class="text-base text-theme-on-surface-variant">
                Teams only you use are deleted with everything in them: recipes, inventory, lists, routines and photos.
            </text>
            <text class="text-base text-theme-on-surface-variant">
                In teams you share, your own lists and routines are deleted. Everything else stays with the team.
            </text>
            <text class="text-base text-theme-on-surface-variant">
                If other people use a team you own, make one of them its owner on the Sunny website first.
            </text>
        </column>

        <outlined-text-input
            ref="delete-account-password"
            label="Password"
            placeholder="Password"
            secure
            :ios-leading-icon="Ios::Lock"
            :android-leading-icon="Android::Lock"
            native:model="password"
        />

        @if ($error)
            <text ref="delete-account-error" class="text-sm text-theme-destructive">{{ $error }}</text>
        @endif

        <button
            ref="delete-account-submit"
            variant="destructive"
            size="lg"
            font="semibold"
            class="w-full"
            @tap="submit"
        >
            Delete my account
        </button>
    </column>
</scroll-view>
