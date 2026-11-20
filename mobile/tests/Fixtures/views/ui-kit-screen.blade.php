@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<column ref="screen" class="{{ Card::classes() }} w-full">
    <text ref="before">Before</text>
    <x-ui.heading ref="heading">{{ $name }}</x-ui.heading>
    <x-ui.heading ref="heading-xl" size="xl" accent>Page title</x-ui.heading>
    <x-ui.heading ref="heading-destructive" class="text-theme-destructive">Download failed</x-ui.heading>
    <x-ui.subheading ref="subheading">Everything in the garage</x-ui.subheading>
    <x-ui.text ref="text" size="sm" max-lines="1">in Garage</x-ui.text>
    <x-ui.text ref="text-strong" variant="strong">Strong</x-ui.text>
    <x-ui.text ref="text-red" color="red">Red</x-ui.text>
    <x-ui.badge ref="badge" color="lime" size="sm">Shopping</x-ui.badge>
    <x-ui.avatar ref="avatar-icon" size="sm" color="amber" :ios="Ios::Cube" :android="Android::ViewInAr" />
    <x-ui.avatar ref="avatar-photo" size="sm" src="https://example.com/photo.jpg" alt="Photo of tent" />
    <x-ui.avatar ref="avatar-initials" circle :name="$name" />
    <x-ui.button ref="button-primary" :ios="Ios::ArrowClockwise" :android="Android::Refresh" :disabled="$saves > 1" tap="save">Save</x-ui.button>
    <x-ui.button ref="button-ghost" variant="ghost" :font="false" navigate="/login">Log in</x-ui.button>
    <x-ui.button ref="button-filled" variant="filled" size="lg" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add a calendar" tap="save">Add calendar</x-ui.button>
    <x-ui.button ref="button-tint" variant="tint" class="w-full" tap="save">Restore</x-ui.button>
    <x-ui.button ref="button-neutral" variant="neutral" tap="save">Select</x-ui.button>
    <x-ui.button ref="button-glass" variant="glass" tone="accent" tap="save">Take photos</x-ui.button>
    <x-ui.button ref="button-outline" variant="outline" tap="save">Choose photo</x-ui.button>
    <x-ui.button ref="button-outline-pill" variant="outline-pill" tap="save">Fill it in myself</x-ui.button>
    <x-ui.button ref="button-plain" variant="plain" tone="destructive" :a11y-label="'Delete '.$name" tap="save">Delete {{ $name }}</x-ui.button>
    <x-ui.button ref="button-icon" variant="glass" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$menu" />
    <x-ui.empty-state ref="empty-state" :ios="Ios::ListBullet" :android="Android::FormatListBulleted" :heading="'No lists match “'.$name.'”'" text="Try a different search." />
    <text ref="after">After</text>
</column>
