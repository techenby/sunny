@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="UI kit" back />

<x-ui.submit-bar ref="ui-kit-submit-bar" tap="tapped('submit-bar')">x-ui.submit-bar</x-ui.submit-bar>

<scroll-view ref="ui-kit" fill class="bg-theme-background">
    <column class="w-full gap-6 px-4 pt-2 pb-8">
        <x-ui.text ref="ui-kit-last-tapped" size="sm" class="px-1">{{ $lastTapped === '' ? 'Tap an example to check it responds.' : 'Last tapped: '.$lastTapped }}</x-ui.text>

        <column ref="ui-kit-select" class="w-full gap-2">
            <x-ui.subheading class="px-1">select, for short choices in forms</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                <select ref="ui-kit-select-time-of-day" label="Time of day" :options="['Morning', 'Afternoon', 'Evening', 'Anytime']" native:model="timeOfDay" />
                <select ref="ui-kit-select-repeats" label="Repeats" :options="['Daily', 'Weekly', 'Monthly']" supporting="Helper text sits below the field." native:model="repeats" />
            </column>
        </column>

        <column ref="ui-kit-form" class="w-full gap-2">
            <x-ui.subheading class="px-1">Form pieces</x-ui.subheading>
            <column class="w-full gap-4">
                <outlined-text-input ref="ui-kit-input" label="label, on a text field" placeholder="placeholder" supporting="supporting, below the field" />
                <column class="w-full gap-1">
                    <x-ui.field-label>x-ui.field-label, for a control with no label of its own</x-ui.field-label>
                    <column class="{{ Card::classes() }} w-full p-4">
                        <x-ui.text>The control</x-ui.text>
                    </column>
                    <x-ui.field-help>x-ui.field-help, below what it explains</x-ui.field-help>
                </column>
                <x-ui.picker-field ref="ui-kit-picker-date" label="x-ui.picker-field, opens a date" value="Today, Nov 20" opens="date" tap="tapped('picker-field date')" />
                <x-ui.picker-field ref="ui-kit-picker-menu" label="x-ui.picker-field, opens a menu" value="Value" tap="tapped('picker-field menu')" />
            </column>
            <x-ui.text size="sm" class="px-1">x-ui.submit-bar is the button pinned at the bottom of this screen. On a form it takes the tab bar’s place.</x-ui.text>
        </column>

        <column ref="ui-kit-sheets" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.sheet-header and x-ui.sheet-confirm</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                <x-ui.sheet-header ref="ui-kit-sheet-header" title="title" close="tapped('sheet-header close')" />
                <x-ui.text size="sm" class="px-4">The sheet’s content goes between the two. The x closes it, and so does swiping down.</x-ui.text>
                <x-ui.sheet-confirm ref="ui-kit-sheet-confirm" help="help, under the button" tap="tapped('sheet-confirm')">x-ui.sheet-confirm</x-ui.sheet-confirm>
            </column>
            <x-ui.text size="sm" class="px-1">loading, the only time the button is unavailable</x-ui.text>
            <column class="{{ Card::classes() }} w-full">
                <x-ui.sheet-confirm ref="ui-kit-sheet-confirm-loading" loading tap="tapped('sheet-confirm loading')">Saving…</x-ui.sheet-confirm>
            </column>
            <x-ui.button ref="ui-kit-sheet-open" variant="secondary" class="w-full" tap="openSheet">Open a sheet</x-ui.button>
            <x-ui.text size="sm" class="px-1">detents="fit" makes a sheet as tall as its content. detents="large" is only for a long list with a search field.</x-ui.text>
        </column>

        <column ref="ui-kit-buttons" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.button</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                <x-ui.text size="sm">variant="primary", the default. The action the screen exists for, one per screen. Disabled, a filled look turns grey with a lighter label.</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-primary" tap="tapped('primary')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-primary-icon" :ios="Ios::Plus" :android="Android::Add" tap="tapped('primary with ios and android')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-primary-disabled" disabled tap="tapped('primary disabled')">disabled</x-ui.button>
                </row>
                <x-ui.text size="sm">variant="secondary". Every other action that does something. With no label it is a circle.</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-secondary" variant="secondary" tap="tapped('secondary')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-secondary-icon" variant="secondary" :ios="Ios::Plus" :android="Android::Add" tap="tapped('secondary with ios and android')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-secondary-icon-only" variant="secondary" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add" tap="tapped('secondary with no label')" />
                    <x-ui.button ref="ui-kit-button-secondary-disabled" variant="secondary" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add" disabled tap="tapped('secondary disabled')" />
                </row>
                <x-ui.button ref="ui-kit-button-secondary-disabled-label" variant="secondary" class="w-full" disabled tap="tapped('secondary disabled')">disabled</x-ui.button>
                <x-ui.text size="sm">variant="glass". Secondary when it floats in a bottom bar. Disabled, it stays glass with a lighter label.</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-glass" variant="glass" tap="tapped('glass')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-glass-icon" variant="glass" :ios="Ios::Pencil" :android="Android::Edit" tap="tapped('glass with ios and android')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-glass-icon-only" variant="glass" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add" tap="tapped('glass with no label')" />
                    <x-ui.button ref="ui-kit-button-glass-disabled" variant="glass" :ios="Ios::ChevronRight" :android="Android::ChevronRight" a11y-label="Next" disabled tap="tapped('glass disabled')" />
                    <x-ui.button ref="ui-kit-button-glass-disabled-label" variant="glass" disabled tap="tapped('glass disabled')">disabled</x-ui.button>
                </row>
                <x-ui.text size="sm">variant="text". Leaving a sheet and small inline edits. With no label it is a row icon, for inside a row or a field only. Disabled, both only get lighter.</x-ui.text>
                <row class="w-full items-center gap-4">
                    <x-ui.button ref="ui-kit-button-text" variant="text" tap="tapped('text')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-text-icon" variant="text" :ios="Ios::Plus" :android="Android::Add" tap="tapped('text with ios and android')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-text-icon-only" variant="text" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" tap="tapped('text with no label')" />
                    <x-ui.button ref="ui-kit-button-text-disabled" variant="text" disabled tap="tapped('text disabled')">disabled</x-ui.button>
                    <x-ui.button ref="ui-kit-button-text-icon-only-disabled" variant="text" :ios="Ios::ChevronUp" :android="Android::KeyboardArrowUp" a11y-label="Move up" disabled tap="tapped('text with no label, disabled')" />
                </row>
                <x-ui.text size="sm">variant="destructive". Only to confirm a delete. The trash icon is built in.</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-destructive" variant="destructive" tap="tapped('destructive')">Delete</x-ui.button>
                    <x-ui.button ref="ui-kit-button-destructive-disabled" variant="destructive" disabled tap="tapped('destructive disabled')">disabled</x-ui.button>
                </row>
            </column>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                <x-ui.text size="sm">Any two looks in one row</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-row-secondary" variant="secondary" class="flex-1" tap="tapped('secondary beside primary')">Secondary</x-ui.button>
                    <x-ui.button ref="ui-kit-button-row-primary" class="flex-1" tap="tapped('primary beside secondary')">Primary</x-ui.button>
                </row>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-row-text" variant="text" class="flex-1 items-center" tap="tapped('text beside destructive')">Cancel</x-ui.button>
                    <x-ui.button ref="ui-kit-button-row-destructive" variant="destructive" class="flex-1" tap="tapped('destructive beside text')">Delete</x-ui.button>
                </row>
                <x-ui.text size="sm">loading</x-ui.text>
                <x-ui.button ref="ui-kit-button-loading" class="w-full" loading tap="tapped('loading')">Saving…</x-ui.button>
                <x-ui.text size="sm">icon-trailing</x-ui.text>
                <x-ui.button ref="ui-kit-button-trailing" class="w-full" :ios="Ios::ArrowRight" :android="Android::ArrowForward" icon-trailing tap="tapped('icon-trailing')">Label</x-ui.button>
                <x-ui.text size="sm">:menu, built with App\Ui\Menu::item() and Menu::delete(), which goes last. On a label, on the more circle of a bottom bar, and on a row icon.</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-menu" variant="secondary" :menu="$menu">Open menu</x-ui.button>
                    <x-ui.button ref="ui-kit-button-menu-icon" variant="glass" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$menu" />
                    <x-ui.button ref="ui-kit-button-menu-row" variant="text" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$menu" />
                </row>
                <x-ui.text size="sm">navigate="/inventory/0", which opens x-ui.not-found</x-ui.text>
                <x-ui.button ref="ui-kit-button-navigate" variant="secondary" class="w-full" navigate="/inventory/0">Open a screen</x-ui.button>
            </column>
        </column>

        <column ref="ui-kit-empty-state" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.empty-state</x-ui.subheading>
            <x-ui.empty-state :ios="Ios::ListBullet" :android="Android::FormatListBulleted" heading="No lists yet" text="Tap + to start a to-do, shopping, or wish list." />
        </column>

        <column ref="ui-kit-type" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.heading, x-ui.subheading, x-ui.text</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full gap-2 p-4">
                <x-ui.heading size="2xl">heading size="2xl"</x-ui.heading>
                <x-ui.heading size="xl">heading size="xl"</x-ui.heading>
                <x-ui.heading size="lg">heading size="lg"</x-ui.heading>
                <x-ui.heading>heading</x-ui.heading>
                <x-ui.subheading size="xl">subheading size="xl"</x-ui.subheading>
                <x-ui.subheading size="lg">subheading size="lg"</x-ui.subheading>
                <x-ui.subheading>subheading</x-ui.subheading>
                <x-ui.subheading size="sm">subheading size="sm"</x-ui.subheading>
                <x-ui.text size="xl">text size="xl"</x-ui.text>
                <x-ui.text size="lg">text size="lg"</x-ui.text>
                <x-ui.text>text</x-ui.text>
                <x-ui.text size="sm">text size="sm"</x-ui.text>
                <x-ui.text variant="strong">text variant="strong"</x-ui.text>
                <x-ui.text variant="subtle">text variant="subtle"</x-ui.text>
                <x-ui.text color="red">text color="red"</x-ui.text>
                <x-ui.text color="green">text color="green"</x-ui.text>
            </column>
        </column>

        <column ref="ui-kit-avatars" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.avatar</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                <x-ui.text size="sm">size="xs", "sm", the default, "lg", "xl", "2xl", with ios and android</x-ui.text>
                <row class="w-full items-center gap-3">
                    @foreach (['xs', 'sm', 'md', 'lg', 'xl', '2xl'] as $size)
                        <x-ui.avatar :size="$size" :ios="Ios::Cube" :android="Android::ViewInAr" />
                    @endforeach
                </row>
                <x-ui.text size="sm">circle, in the same sizes, with name</x-ui.text>
                <row class="w-full items-center gap-3">
                    @foreach (['xs', 'sm', 'md', 'lg', 'xl', '2xl'] as $size)
                        <x-ui.avatar circle :size="$size" name="Camping gear" />
                    @endforeach
                </row>
                <x-ui.text size="sm">color="amber", "red", "sky", "lime", as a tile and as a circle</x-ui.text>
                <row class="w-full items-center gap-3">
                    @foreach (['amber', 'red', 'sky', 'lime'] as $color)
                        <x-ui.avatar :color="$color" :ios="Ios::Archivebox" :android="Android::Inventory2" />
                        <x-ui.avatar circle :color="$color" initials="AS" />
                    @endforeach
                </row>
            </column>
        </column>

        <column ref="ui-kit-badges" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.badge</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                <row class="w-full items-center gap-2">
                    <x-ui.badge size="sm">size="sm"</x-ui.badge>
                    <x-ui.badge>default</x-ui.badge>
                    <x-ui.badge size="lg">size="lg"</x-ui.badge>
                    <x-ui.badge rounded>rounded</x-ui.badge>
                </row>
                <row class="w-full items-center gap-2">
                    @foreach (['primary', 'red', 'amber', 'lime', 'sky', 'purple'] as $color)
                        <x-ui.badge :color="$color" size="sm">{{ $color }}</x-ui.badge>
                    @endforeach
                </row>
            </column>
        </column>

        <column ref="ui-kit-alert" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.alert</x-ui.subheading>
            <x-ui.alert :ios="Ios::CheckmarkCircle" :android="Android::CheckCircle" title="title" message="message, with ios and android for the icon. For information." />
            <x-ui.alert tone="offline" :ios="Ios::WifiSlash" :android="Android::WifiOff" title="tone offline" message="Neutral: offline is not an error." />
            <x-ui.alert tone="error" :ios="Ios::ExclamationmarkCircle" :android="Android::ErrorOutline" title="tone error" message="For something that went wrong. Offline and information use the default." />
        </column>

        <column ref="ui-kit-offline" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.offline</x-ui.subheading>
            <x-ui.offline ref="ui-kit-offline-alert" />
            <x-ui.text size="sm" class="px-1">extra="Nothing’s been sent yet.", for after a send that failed</x-ui.text>
            <x-ui.offline ref="ui-kit-offline-extra" extra="Nothing’s been sent yet." />
        </column>

        <column ref="ui-kit-cards" class="w-full gap-2">
            <x-ui.subheading class="px-1">App\Ui\Card::classes()</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full p-4">
                <x-ui.text>Card::classes()</x-ui.text>
            </column>
            <column class="{{ Card::classes('filled') }} w-full p-4">
                <x-ui.text>Card::classes('filled')</x-ui.text>
            </column>
            <column class="{{ Card::classes('outline') }} w-full p-4">
                <x-ui.text>Card::classes('outline')</x-ui.text>
            </column>
        </column>

        <column ref="ui-kit-rows" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.row</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                <x-ui.row ref="ui-kit-row-tile" url="/inventory/0" headline="tile" supporting="A square tile, for things that can have a photo" :ios="Ios::ForkKnife" :android="Android::Restaurant" tile trailing="chevron" />
                <x-ui.row ref="ui-kit-row-circle" url="/inventory/0" headline="icon-background" supporting="A filled circle, for categories" :ios="Ios::ListBullet" :android="Android::FormatListBulleted" icon-background="purple-500" :owner="['name' => 'Andy Swick', 'initials' => 'AS']" trailing="chevron" />
                <x-ui.row ref="ui-kit-row-number" :number="1" headline="number, with a menu" press-ref="ui-kit-row-number-open" tap="tapped('row number')" :menu="$menu" />
                <x-ui.row ref="ui-kit-row-photo" photo="https://picsum.photos/id/200/128" alt="A photo" badge="Main photo" :menu="$menu" menu-label="More for the photo" />
                <x-ui.row ref="ui-kit-row-action" headline="action" supporting="One action keeps one bare icon" :ios="Ios::Envelope" :android="Android::Email" :action="['ref' => 'ui-kit-row-action-x', 'ios' => Ios::Xmark, 'android' => Android::Close, 'label' => 'Remove', 'tap' => 'tapped(\'row action\')']" last />
            </column>
            <x-ui.text size="sm" class="px-1">The divider starts under the text: past a 40pt icon, a number or a photo. Two or more actions go in :menu; one keeps a bare icon (:action). Rows that open something use a made-up item, which shows x-ui.not-found.</x-ui.text>
        </column>

        <column ref="ui-kit-inventory-rows" class="w-full gap-2">
            <x-ui.subheading class="px-1">native.inventory-item-row</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @include('native.inventory-item-row', ['item' => $item, 'ref' => 'ui-kit-inventory-item-row', 'from' => null])
            </column>
        </column>

        <column ref="ui-kit-settings-rows" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.row, settings</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                <x-ui.row ref="ui-kit-settings-row-plain" headline="headline only" />
                <x-ui.row
                    ref="ui-kit-settings-row-chevron"
                    :headline="'trailing \'chevron\''"
                    supporting="supporting, with ios and android"
                    :ios="Ios::Person"
                    :android="Android::Person"
                    trailing="chevron"
                    :tap="'tapped(\'settings-row chevron\')'"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-external"
                    :headline="'trailing \'external\''"
                    :ios="Ios::HandRaised"
                    :android="Android::Policy"
                    trailing="external"
                    :tap="'tapped(\'settings-row external\')'"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-refresh"
                    :headline="'trailing \'refresh\''"
                    :ios="Ios::Calendar"
                    :android="Android::CalendarMonth"
                    trailing="refresh"
                    :tap="'tapped(\'settings-row refresh\')'"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-check"
                    :headline="'trailing \'check\''"
                    monogram="F"
                    trailing="check"
                    :tap="'tapped(\'settings-row check\')'"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-trailing-text"
                    headline="trailingText"
                    :ios="Ios::Person2"
                    :android="Android::Group"
                    trailing-text="Rename"
                    :tap="'tapped(\'settings-row trailingText\')'"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-toggle"
                    headline="toggle"
                    :ios="Ios::Ladybug"
                    :android="Android::BugReport"
                    :toggle="['value' => $toggled, 'change' => 'setToggled']"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-primary"
                    :headline="'tone \'primary\''"
                    :ios="Ios::Plus"
                    :android="Android::Add"
                    tone="add"
                    :tap="'tapped(\'settings-row primary\')'"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-destructive"
                    :headline="'tone \'destructive\''"
                    :ios="Ios::RectanglePortraitAndArrowRight"
                    :android="Android::Logout"
                    tone="destructive"
                    :tap="'tapped(\'settings-row destructive\')'"
                />
                <x-ui.row
                    ref="ui-kit-settings-row-disabled"
                    headline="disabled"
                    :ios="Ios::Person"
                    :android="Android::Person"
                    trailing="chevron"
                    disabled
                    :tap="'tapped(\'settings-row disabled\')'"
                    last
                />
            </column>
        </column>
    </column>
</scroll-view>

<native:bottom-sheet ref="ui-kit-sheet" :visible="$showSheet" @dismiss="closeSheet" detents="fit">
    @if ($showSheet)
        <column class="w-full bg-theme-background">
            <x-ui.sheet-header ref="ui-kit-sheet-close" title="x-ui.sheet-header" close="closeSheet" />
            <column class="w-full px-4 pt-4">
                <outlined-text-input ref="ui-kit-sheet-input" label="A field, to check the keyboard" native:model="sheetText" />
            </column>
            <x-ui.sheet-confirm ref="ui-kit-sheet-save" tap="confirmSheet">x-ui.sheet-confirm</x-ui.sheet-confirm>
        </column>
    @endif
</native:bottom-sheet>
