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
    <x-ui.button ref="button-primary" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add a calendar" tap="save">Add calendar</x-ui.button>
    <x-ui.button ref="button-secondary" variant="secondary" class="w-full" tap="save">Restore</x-ui.button>
    <x-ui.button ref="button-glass" variant="glass" navigate="/login">Edit</x-ui.button>
    <x-ui.button ref="button-destructive" variant="destructive" tap="save">Delete</x-ui.button>
    <x-ui.button ref="button-text" variant="text" :a11y-label="'Rename '.$name" tap="save">Rename {{ $name }}</x-ui.button>
    <x-ui.button ref="button-disabled" :disabled="$saves > 1" tap="save">Save</x-ui.button>
    <x-ui.button ref="button-loading" variant="destructive" loading tap="save">Deleting…</x-ui.button>
    <x-ui.button ref="button-circle" variant="glass" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$menu" />
    <x-ui.button ref="button-row-icon" variant="text" :ios="Ios::Xmark" :android="Android::Close" a11y-label="Remove" tap="save" />
    <x-ui.button ref="button-glass-disabled" variant="glass" disabled tap="save">Today</x-ui.button>
    <x-ui.button ref="button-row-icon-disabled" variant="text" :ios="Ios::ChevronUp" :android="Android::KeyboardArrowUp" a11y-label="Move up" disabled tap="save" />
    <x-ui.empty-state ref="empty-state" :ios="Ios::ListBullet" :android="Android::FormatListBulleted" :heading="'No lists match “'.$name.'”'" text="Try a different search." />
    <text ref="after">After</text>
</column>
