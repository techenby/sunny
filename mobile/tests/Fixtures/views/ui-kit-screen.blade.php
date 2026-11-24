@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<column ref="screen" class="{{ Card::classes() }} w-full">
    <text ref="before">Before</text>
    <x-ui.heading ref="heading">{{ $name }}</x-ui.heading>
    <x-ui.heading ref="heading-xl" size="xl">Page title</x-ui.heading>
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
    <x-ui.offline ref="offline" extra="Nothing’s been sent yet." />
    <x-ui.field-label ref="field-label">Where does it go?</x-ui.field-label>
    <x-ui.field-help ref="field-help">Not sure? Leave it as a thing.</x-ui.field-help>
    <x-ui.picker-field ref="picker-field" label="Starts on" value="Today, Nov 20" opens="date" a11y-hint="Choose when this routine starts" tap="save" />
    <x-ui.submit-bar ref="submit-bar" :disabled="$saves > 5">Save</x-ui.submit-bar>
    <x-ui.sheet-header ref="sheet-close" :title="'Rename '.$name" close="save" />
    <x-ui.sheet-confirm ref="sheet-confirm" :ios="Ios::Plus" :android="Android::Add" help="Or tap one above to look inside it" tap="save">Put it in {{ $name }}</x-ui.sheet-confirm>
    <x-ui.sheet-confirm ref="sheet-confirm-saving" :loading="$saves > 2" tap="save">Save</x-ui.sheet-confirm>
    <x-ui.alert ref="alert-error" tone="error" :ios="Ios::ExclamationmarkCircle" :android="Android::ErrorOutline" title="Couldn’t save" message="Try again in a moment." />
    <x-ui.badge ref="badge-primary" color="primary" size="sm">Current step</x-ui.badge>
    <column ref="rows" class="w-full">
        <x-ui.row ref="row-add" headline="Invite someone" :ios="Ios::PlusCircle" :android="Android::AddCircle" tone="add" tap="save" />
        <x-ui.row ref="row-circle" headline="Morning" :ios="Ios::SunMax" :android="Android::WbSunny" icon-background="theme-primary" icon-color="theme-on-primary" url="/routines/1" />
        <x-ui.row ref="row-number" :number="1" headline="Brush teeth" plain press-ref="row-number-open" tap="save" :menu="$menu" />
        <x-ui.row ref="row-photo" photo="https://example.com/photo.jpg" alt="Photo 1" badge="Main photo" :menu="$menu" menu-ref="row-photo-more" menu-label="More for photo 1" />
        <x-ui.row ref="row-action" headline="jo@example.com" :action="['ref' => 'row-action-x', 'ios' => Ios::Xmark, 'android' => Android::Close, 'label' => 'Cancel the invitation', 'tap' => 'save']" />
        <x-ui.row ref="row-rename" :headline="$name" trailing-text="Rename" tap="save" last />
    </column>
    <text ref="after">After</text>
</column>
