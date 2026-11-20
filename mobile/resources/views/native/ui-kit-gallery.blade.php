@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="UI kit" back />

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

        <column ref="ui-kit-platform-buttons" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.button, drawn by the platform</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                @foreach (['primary', 'secondary', 'destructive', 'ghost'] as $variant)
                    <x-ui.text size="sm">variant="{{ $variant }}"</x-ui.text>
                    <x-ui.button ref="ui-kit-button-{{ $variant }}" :variant="$variant" class="w-full" tap="tapped('{{ $variant }}')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-{{ $variant }}-icon" :variant="$variant" class="w-full" :ios="Ios::ArrowClockwise" :android="Android::Refresh" tap="tapped('{{ $variant }} with ios and android')">With an icon</x-ui.button>
                    <x-ui.button ref="ui-kit-button-{{ $variant }}-disabled" :variant="$variant" class="w-full" disabled tap="tapped('{{ $variant }} disabled')">disabled</x-ui.button>
                @endforeach
                <x-ui.text size="sm">variant="primary" loading</x-ui.text>
                <x-ui.button ref="ui-kit-button-primary-loading" class="w-full" loading tap="tapped('primary loading')">loading</x-ui.button>
                <x-ui.text size="sm">variant="ghost" :font="false"</x-ui.text>
                <x-ui.button ref="ui-kit-button-ghost-regular" variant="ghost" :font="false" class="w-full" tap="tapped('ghost without the semibold font')">Label</x-ui.button>
            </column>
        </column>

        <column ref="ui-kit-drawn-buttons" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.button, drawn by the app</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                @foreach (['filled', 'tint', 'neutral', 'glass', 'outline', 'outline-pill', 'plain'] as $variant)
                    <x-ui.text size="sm">variant="{{ $variant }}": label, with an icon, no label</x-ui.text>
                    <row class="w-full items-center gap-3">
                        <x-ui.button ref="ui-kit-button-{{ $variant }}" :variant="$variant" tap="tapped('{{ $variant }}')">Label</x-ui.button>
                        <x-ui.button ref="ui-kit-button-{{ $variant }}-icon" :variant="$variant" :ios="Ios::Plus" :android="Android::Add" tap="tapped('{{ $variant }} with ios and android')">Label</x-ui.button>
                        <x-ui.button ref="ui-kit-button-{{ $variant }}-icon-only" :variant="$variant" :ios="Ios::Plus" :android="Android::Add" a11y-label="Add" tap="tapped('{{ $variant }} with no label')" />
                    </row>
                @endforeach
            </column>
            <column class="{{ Card::classes() }} w-full gap-3 p-4">
                <x-ui.text size="sm">size="sm", the default, size="lg"</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-size-sm" variant="filled" size="sm" :ios="Ios::Plus" :android="Android::Add" tap="tapped('size sm')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-size-base" variant="filled" :ios="Ios::Plus" :android="Android::Add" tap="tapped('default size')">Label</x-ui.button>
                    <x-ui.button ref="ui-kit-button-size-lg" variant="filled" size="lg" :ios="Ios::Plus" :android="Android::Add" tap="tapped('size lg')">Label</x-ui.button>
                </row>
                <x-ui.text size="sm">variant="plain" with tone="accent" (its default), "destructive", "neutral", "muted"</x-ui.text>
                <row class="w-full items-center gap-4">
                    @foreach (['accent', 'destructive', 'neutral', 'muted'] as $tone)
                        <x-ui.button ref="ui-kit-button-tone-{{ $tone }}" variant="plain" :tone="$tone" tap="tapped('plain with tone {{ $tone }}')">Label</x-ui.button>
                    @endforeach
                </row>
                <x-ui.text size="sm">variant="glass" tone="accent"</x-ui.text>
                <x-ui.button ref="ui-kit-button-glass-accent" variant="glass" tone="accent" class="w-full" :ios="Ios::CameraFill" :android="Android::PhotoCamera" tap="tapped('glass with tone accent')">Label</x-ui.button>
                <x-ui.text size="sm">icon-trailing</x-ui.text>
                <x-ui.button ref="ui-kit-button-trailing" variant="filled" class="w-full" :ios="Ios::ArrowRight" :android="Android::ArrowForward" icon-trailing tap="tapped('icon-trailing')">Label</x-ui.button>
                <x-ui.text size="sm">:menu, on a label and on an icon button</x-ui.text>
                <row class="w-full items-center gap-3">
                    <x-ui.button ref="ui-kit-button-menu" variant="neutral" :menu="$menu">Open menu</x-ui.button>
                    <x-ui.button ref="ui-kit-button-menu-icon" variant="glass" :ios="Ios::Ellipsis" :android="Android::MoreHoriz" a11y-label="More" :menu="$menu" />
                </row>
                <x-ui.text size="sm">navigate="/inventory/0", which opens x-ui.not-found</x-ui.text>
                <x-ui.button ref="ui-kit-button-navigate" variant="tint" class="w-full" navigate="/inventory/0">Open a screen</x-ui.button>
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
                <x-ui.heading accent>heading accent</x-ui.heading>
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
                    @foreach (['red', 'amber', 'lime', 'sky', 'purple'] as $color)
                        <x-ui.badge :color="$color" size="sm">{{ $color }}</x-ui.badge>
                    @endforeach
                </row>
            </column>
        </column>

        <column ref="ui-kit-alert" class="w-full gap-2">
            <x-ui.subheading class="px-1">x-ui.alert</x-ui.subheading>
            <x-ui.alert :ios="Ios::WifiSlash" :android="Android::WifiOff" title="title" message="message, with ios and android for the icon." />
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

        <column ref="ui-kit-owned-rows" class="w-full gap-2">
            <x-ui.subheading class="px-1">native.owned-row</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @include('native.owned-row', ['ref' => 'ui-kit-owned-row-tile', 'url' => '/inventory/0', 'headline' => 'tile', 'supporting' => 'A square tile, for things that can have a photo', 'ios' => Ios::ForkKnife, 'android' => Android::Restaurant, 'tile' => true, 'owner' => null, 'last' => false])
                @include('native.owned-row', ['ref' => 'ui-kit-owned-row-circle', 'url' => '/inventory/0', 'headline' => 'iconBackground', 'supporting' => 'A filled circle, for categories', 'ios' => Ios::ListBullet, 'android' => Android::FormatListBulleted, 'iconBackground' => 'purple-500', 'owner' => ['name' => 'Andy Swick', 'initials' => 'AS'], 'last' => false])
                @include('native.owned-row', ['ref' => 'ui-kit-owned-row-glyph', 'url' => '/inventory/0', 'headline' => 'Neither', 'supporting' => 'A bare glyph, with an owner who is the whole team', 'ios' => Ios::ChecklistChecked, 'android' => Android::Checklist, 'owner' => ['name' => 'Family', 'initials' => null], 'last' => true])
            </column>
            <x-ui.text size="sm" class="px-1">Rows open a made-up item, which shows x-ui.not-found.</x-ui.text>
        </column>

        <column ref="ui-kit-inventory-rows" class="w-full gap-2">
            <x-ui.subheading class="px-1">native.inventory-item-row</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @include('native.inventory-item-row', ['item' => $item, 'ref' => 'ui-kit-inventory-item-row', 'from' => null])
            </column>
        </column>

        <column ref="ui-kit-settings-rows" class="w-full gap-2">
            <x-ui.subheading class="px-1">native.settings-row</x-ui.subheading>
            <column class="{{ Card::classes() }} w-full">
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-plain', 'headline' => 'headline only'])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-chevron', 'headline' => "trailing 'chevron'", 'supporting' => 'supporting, with ios and android', 'ios' => Ios::Person, 'android' => Android::Person, 'trailing' => 'chevron', 'tap' => "tapped('settings-row chevron')"])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-external', 'headline' => "trailing 'external'", 'ios' => Ios::HandRaised, 'android' => Android::Policy, 'trailing' => 'external', 'tap' => "tapped('settings-row external')"])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-refresh', 'headline' => "trailing 'refresh'", 'ios' => Ios::Calendar, 'android' => Android::CalendarMonth, 'trailing' => 'refresh', 'tap' => "tapped('settings-row refresh')"])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-check', 'headline' => "trailing 'check'", 'monogram' => 'F', 'trailing' => 'check', 'tap' => "tapped('settings-row check')"])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-trailing-text', 'headline' => 'trailingText', 'ios' => Ios::Person2, 'android' => Android::Group, 'trailingText' => 'Rename', 'tap' => "tapped('settings-row trailingText')"])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-toggle', 'headline' => 'toggle', 'ios' => Ios::Ladybug, 'android' => Android::BugReport, 'toggle' => ['value' => $toggled, 'change' => 'setToggled']])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-primary', 'headline' => "tone 'primary'", 'ios' => Ios::Plus, 'android' => Android::Add, 'tone' => 'primary', 'tap' => "tapped('settings-row primary')"])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-destructive', 'headline' => "tone 'destructive'", 'ios' => Ios::RectanglePortraitAndArrowRight, 'android' => Android::Logout, 'tone' => 'destructive', 'tap' => "tapped('settings-row destructive')"])
                @include('native.settings-row', ['ref' => 'ui-kit-settings-row-disabled', 'headline' => 'disabled', 'ios' => Ios::Person, 'android' => Android::Person, 'trailing' => 'chevron', 'disabled' => true, 'tap' => "tapped('settings-row disabled')", 'last' => true])
            </column>
        </column>
    </column>
</scroll-view>
