@use('App\Actions\DuplicateItem')
@use('App\Enums\ItemType')
@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\NativeComponents\Inventory')
@use('App\Ui\Card')

@php($item = $this->item)

<native:top-bar :title="$item['name'] ?? 'Inventory'" display-mode="large" back />

@if ($item)
    @if ($selecting)
    @include('native.inventory-selection-bar', ['selectedItems' => $this->selectedItems, 'selectedAll' => $this->selectedAll])
    @else
    <native:bottom-bar>
        <row class="w-full items-center gap-3 px-4 pb-2">
            <x-ui.button
                ref="edit-item"
                variant="glass"
                class="flex-1"
                :ios="Ios::Pencil"
                :android="Android::Edit"
                a11y-label="Edit"
                :navigate="'/inventory/'.$item['id'].'/edit'"
            >Edit</x-ui.button>
            <x-ui.button
                ref="item-more"
                variant="glass"
                :ios="Ios::Ellipsis"
                :android="Android::MoreHoriz"
                a11y-label="More"
                :menu="$this->moreMenu()"
            />
        </row>
    </native:bottom-bar>
    @endif

    <scroll-view ref="item-detail" fill class="bg-theme-background">
        <column class="w-full gap-7 px-4 pt-2 {{ $selecting ? 'pb-[240]' : 'pb-8' }}">
            <column class="w-full gap-3">
                <row class="w-full items-center gap-2">
                    <x-ui.avatar
                        size="xs"
                        :color="$item['type']->color()"
                        :ios="$item['type']->iosIcon()"
                        :android="$item['type']->androidIcon()"
                    />
                    <x-ui.text ref="item-summary" class="flex-1">
                        {{ $this->children ? $item['type']->label().' · '.Inventory::summary([...$item, 'children_count' => count($this->children)]) : $item['type']->label() }}
                    </x-ui.text>
                </row>

                @include('native.queued-change', ['refPrefix' => 'item', 'queuedChange' => $this->queuedChange])

                @if ($error !== '')
                    <text ref="item-error" class="text-base text-theme-destructive">{{ $error }}</text>
                @endif

                @php($photos = $this->photos)
                @if ($photos !== [])
                    @php($shown = $photos[$photoIndex] ?? $photos[0])
                    <column class="w-full">
                        <image
                            ref="item-photo"
                            :src="$shown['url']"
                            :alt="'Photo '.($shown['number']).' of '.$item['name']"
                            class="w-full h-[240] rounded-[18] object-cover"
                        />
                        @if (count($photos) > 1)
                            <row class="absolute top-[12] right-[12] rounded-full bg-black/60 px-2.5 py-1">
                                <text ref="item-photo-count" font="semibold" class="text-sm text-white">{{ $shown['number'] }} / {{ count($photos) }}</text>
                            </row>
                            @if ($shown['number'] === 1)
                                <row class="absolute bottom-[12] left-[12] items-center gap-1 rounded-full bg-black/60 px-2.5 py-1">
                                    <icon :ios="Ios::StarFill" :android="Android::Star" :size="12" class="text-white" />
                                    <text ref="item-photo-primary" font="semibold" class="text-sm text-white">Main photo</text>
                                </row>
                            @endif
                        @endif
                    </column>

                    @if (count($photos) > 1)
                        <scroll-view ref="item-photo-strip" horizontal :shows-indicators="false" class="w-full gap-2">
                            @foreach ($photos as $index => $photo)
                                <pressable
                                    ref="item-photo-thumb-{{ $index }}"
                                    a11y-label="Show photo {{ $photo['number'] }}{{ $index === $photoIndex ? ', showing' : '' }}"
                                    class="h-16 w-16 rounded-xl {{ $index === $photoIndex ? 'border-2 border-theme-primary' : '' }}"
                                    @tap="showPhoto({{ $index }})"
                                >
                                    <image :src="$photo['thumb']" :alt="'Photo '.$photo['number']" class="h-full w-full rounded-xl object-cover" />
                                </pressable>
                            @endforeach
                        </scroll-view>
                    @endif
                @endif
            </column>

            @if ($this->starterStorage)
                <column ref="item-empty-place" class="w-full gap-4">
                    <column class="w-full gap-2 px-1">
                        <x-ui.heading size="xl">Nothing stored here yet</x-ui.heading>
                        <x-ui.text size="lg">Storage is anything that holds things, like a box, shelf or drawer. Put Things in it, then search to see where anything lives.</x-ui.text>
                    </column>
                    <column class="w-full gap-2">
                        <x-ui.text class="px-1">Tap one to add it to {{ $item['name'] }}</x-ui.text>
                        <column class="{{ Card::classes() }} w-full">
                            @foreach ($this->starterStorage as $index => $name)
                                <pressable
                                    ref="item-starter-{{ $index }}"
                                    a11y-label="Add {{ $name }}"
                                    class="min-h-12 w-full justify-center px-4 py-2"
                                    @tap="addStarterStorage({{ $index }})"
                                >
                                    <row class="w-full items-center gap-4">
                                        <x-ui.avatar :color="ItemType::Bin->color()" :ios="ItemType::Bin->iosIcon()" :android="ItemType::Bin->androidIcon()" />
                                        <x-ui.heading size="lg" class="flex-1">{{ $name }}</x-ui.heading>
                                        <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary-text" />
                                    </row>
                                </pressable>
                                @unless ($loop->last)
                                    <divider class="ml-[72]" />
                                @endunless
                            @endforeach
                        </column>
                    </column>
                    <column class="{{ Card::classes() }} w-full">
                        <pressable
                            ref="item-add"
                            :a11y-label="'Add to '.$item['name']"
                            class="min-h-12 w-full justify-center px-4 py-2"
                            :menu="$this->addMenu()"
                        >
                            <row class="w-full items-center gap-4">
                                <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                                    <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary-text" />
                                </column>
                                <text font="semibold" class="flex-1 text-lg text-theme-primary-text">Add to {{ $item['name'] }}</text>
                            </row>
                        </pressable>
                    </column>
                </column>
            @endif

            <column class="w-full gap-3">
                <row class="w-full items-center gap-3 px-1">
                    <x-ui.heading size="lg" class="flex-1">Where it is</x-ui.heading>
                    <x-ui.button
                        ref="item-move"
                        variant="secondary"
                        :ios="Ios::ArrowRight"
                        :android="Android::ArrowForward"
                        a11y-label="Move"
                        a11y-hint="Choose a new place for this"
                        tap="startMove"
                    >Move</x-ui.button>
                </row>
                <column class="{{ Card::classes() }} w-full py-2">
                    @forelse ($this->path as $ancestor)
                        <pressable
                            ref="{{ $loop->last ? 'item-parent' : 'item-ancestor-'.$ancestor['id'] }}"
                            @tap="openAncestor({{ $ancestor['id'] }})"
                            :a11y-label="$ancestor['name']"
                            class="min-h-12 w-full justify-center px-4 py-1"
                        >
                            <row class="w-full items-center gap-4">
                                <x-ui.avatar
                                    :color="$ancestor['type']->color()"
                                    :ios="$ancestor['type']->iosIcon()"
                                    :android="$ancestor['type']->androidIcon()"
                                />
                                <x-ui.heading size="lg" class="flex-1">{{ $ancestor['name'] }}</x-ui.heading>
                                <icon :ios="Ios::ChevronRight" :android="Android::ChevronRight" :size="14" class="text-theme-on-surface-variant" />
                            </row>
                        </pressable>
                        <column class="ml-[35] h-[10] w-[2] bg-theme-outline" />
                    @empty
                        @if ($item['type'] === ItemType::Location)
                            <row ref="item-home" class="w-full items-start gap-4 px-4 py-2">
                                <x-ui.avatar :ios="Ios::House" :android="Android::Home" />
                                <column class="flex-1 gap-0.5">
                                    <x-ui.heading size="lg">Home</x-ui.heading>
                                    <x-ui.text>One of the places in your home</x-ui.text>
                                </column>
                            </row>
                        @else
                            <row ref="item-unplaced" class="w-full items-center gap-4 px-4 py-2">
                                <x-ui.avatar :ios="Ios::House" :android="Android::Home" />
                                <column class="flex-1 gap-0.5">
                                    <x-ui.heading size="lg">Not in a place yet</x-ui.heading>
                                    <x-ui.text>Tap Move to put it away.</x-ui.text>
                                </column>
                            </row>
                        @endif
                        <column class="ml-[35] h-[10] w-[2] bg-theme-outline" />
                    @endforelse
                    <row ref="item-here" class="w-full items-center gap-4 px-4 py-2">
                        <column class="h-10 w-10 items-center justify-center rounded-full border-2 border-theme-primary">
                            <column class="h-3 w-3 rounded-full bg-theme-primary" />
                        </column>
                        <column class="flex-1 gap-0.5">
                            <text font="semibold" class="text-lg text-theme-on-surface">{{ $item['name'] }}</text>
                            <text font="semibold" class="text-base text-theme-primary-text">You’re here</text>
                        </column>
                    </row>
                </column>
            </column>

            @unless ($this->starterStorage)
            <column class="w-full gap-3">
                <row class="w-full items-center gap-3 px-1">
                    <x-ui.heading size="lg" class="flex-1">{{ $this->children ? 'What’s inside ('.count($this->children).')' : 'What’s inside' }}</x-ui.heading>
                    @if ($selecting)
                        <x-ui.button ref="item-select-done" variant="secondary" a11y-label="Done" tap="stopSelecting">Done</x-ui.button>
                    @elseif ($this->children)
                        <x-ui.button
                            ref="item-select"
                            variant="secondary"
                            :ios="Ios::CheckmarkCircle"
                            :android="Android::CheckCircleOutline"
                            a11y-label="Select"
                            a11y-hint="Pick several things to move or delete"
                            tap="startSelecting"
                        >Select</x-ui.button>
                    @endif
                </row>
                <column class="{{ Card::classes() }} w-full">
                    @if ($selecting)
                    @foreach ($this->children as $child)
                        @include('native.inventory-item-tick-row', [
                            'item' => $child,
                            'ref' => 'item-child-'.$child['id'],
                            'selected' => in_array($child['id'], $selectedIds, true),
                            'corners' => trim(($loop->first ? 'rounded-t-[17]' : '').' '.($loop->last ? 'rounded-b-[17]' : '')),
                        ])
                        @unless ($loop->last)
                            <divider class="ml-[112]" />
                        @endunless
                    @endforeach
                    @else
                    @foreach ($this->children as $child)
                        @include('native.inventory-item-row', ['item' => $child, 'ref' => 'item-child-'.$child['id'], 'from' => $item['id']])
                        <divider class="ml-[72]" />
                    @endforeach
                    <pressable
                        ref="item-add"
                        :a11y-label="'Add to '.$item['name']"
                        class="min-h-12 w-full justify-center px-4 py-2"
                        :menu="$this->addMenu()"
                    >
                        <row class="w-full items-center gap-4">
                            <column class="h-10 w-10 items-center justify-center rounded-lg bg-theme-primary/15">
                                <icon :ios="Ios::Plus" :android="Android::Add" :size="20" class="text-theme-primary-text" />
                            </column>
                            <text font="semibold" class="flex-1 text-lg text-theme-primary-text">Add to {{ $item['name'] }}</text>
                        </row>
                    </pressable>
                    @endif
                </column>
            </column>
            @endunless

            @if ($item['metadata'])
                <column class="w-full gap-3">
                    <x-ui.heading size="lg" class="px-1">Details</x-ui.heading>
                    <column class="{{ Card::classes() }} w-full">
                        @foreach ($item['metadata'] as $key => $value)
                            <row ref="metadata-{{ $key }}" class="w-full flex-wrap items-center gap-3 px-4 py-3">
                                <text class="flex-1 text-base text-theme-on-surface-variant">{{ ucfirst($key) }}</text>
                                <x-ui.heading>{{ $value }}</x-ui.heading>
                            </row>
                            @unless ($loop->last)
                                <divider class="ml-4" />
                            @endunless
                        @endforeach
                    </column>
                </column>
            @endif

            @if ($this->photos === [] && ! $selecting)
                <pressable
                    ref="item-add-photo"
                    a11y-label="Add a photo"
                    class="{{ Card::classes('outline') }} min-h-12 w-full justify-center px-4 py-2"
                    @tap="editPhoto"
                >
                    <row class="w-full items-start gap-4">
                        <x-ui.avatar :ios="Ios::Camera" :android="Android::PhotoCamera" />
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">Add a photo</x-ui.heading>
                            <x-ui.text>Helps you spot it on the shelf</x-ui.text>
                        </column>
                    </row>
                </pressable>
            @endif
        </column>
    </scroll-view>

    @include('native.parent-picker-sheet', [
        'refPrefix' => 'move',
        'topLevelLabel' => $item['type'] === ItemType::Location ? 'Put it with the places in your home' : null,
        ...($movingSelected ? $this->selectedMoveCopy : []),
        'selectedParent' => $this->selectedParent,
        'browsedParent' => $this->browsedParent,
        'parentPickerRows' => $this->parentPickerRows,
        'parentTrail' => $this->parentTrail,
    ])

    <native:bottom-sheet ref="item-copies-sheet" :visible="$showCopies" @dismiss="closeCopies" detents="fit">
        @if ($showCopies)
            @php($thumb = $this->photos[0]['thumb'] ?? null)
            <column class="w-full bg-theme-background">
                <x-ui.sheet-header ref="item-copies-close" title="Make copies" close="closeCopies" />
                <column class="w-full gap-5 px-4 pt-2">
                    <x-ui.text class="px-1">Each copy gets the same name, details and photos, but starts empty inside.</x-ui.text>

                    <row ref="item-copies-source" class="{{ Card::classes() }} w-full items-center gap-3 px-4 py-3">
                        <x-ui.avatar
                            size="lg"
                            :src="$thumb"
                            :alt="'Photo of '.$item['name']"
                            :color="$item['type']->color()"
                            :ios="$item['type']->iosIcon()"
                            :android="$item['type']->androidIcon()"
                        />
                        <column class="flex-1 gap-0.5">
                            <x-ui.heading size="lg">{{ $item['name'] }}</x-ui.heading>
                            <x-ui.text>Going {{ $this->copiesPlace() }}</x-ui.text>
                        </column>
                    </row>

                    <column class="w-full gap-3">
                        <row ref="item-copies-stepper" class="{{ Card::classes() }} w-full items-center justify-between p-4">
                            <x-ui.button
                                ref="item-copies-fewer"
                                variant="secondary"
                                :ios="Ios::Minus"
                                :android="Android::Remove"
                                a11y-label="Fewer copies"
                                tap="changeCopies(-1)"
                                :disabled="$copies <= 1"
                            />
                            <column class="items-center gap-0.5">
                                <text ref="item-copies-count" font="accent" class="text-5xl text-theme-on-surface">{{ $copies }}</text>
                                <text font="medium" class="text-base text-theme-on-surface-variant">{{ $copies === 1 ? 'copy' : 'copies' }}</text>
                            </column>
                            <x-ui.button
                                ref="item-copies-more"
                                variant="secondary"
                                :ios="Ios::Plus"
                                :android="Android::Add"
                                a11y-label="More copies"
                                tap="changeCopies(1)"
                                :disabled="$copies >= DuplicateItem::MAX_COPIES"
                            />
                        </row>
                        <text font="medium" class="w-full text-center text-xs text-theme-on-surface-variant">Up to {{ DuplicateItem::MAX_COPIES }} at a time</text>
                    </column>

                    @if ($copiesProblem !== null)
                        @php($note = $this->copiesNote())
                        <row ref="item-copies-problem" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
                            <native:icon
                                :ios="$copiesProblem === 'offline' ? Ios::WifiSlash : Ios::ExclamationmarkCircle"
                                :android="$copiesProblem === 'offline' ? Android::WifiOff : Android::ErrorOutline"
                                :size="20"
                                class="text-theme-primary-text"
                            />
                            <column class="flex-1 gap-0.5">
                                <text font="semibold" class="text-base text-theme-primary-text">{{ $note['title'] }}</text>
                                <text class="text-base text-theme-on-surface">{{ $note['message'] }}</text>
                            </column>
                        </row>
                    @endif
                </column>

                <x-ui.sheet-confirm
                    ref="item-copies-submit"
                    :ios="$copiesProblem === null ? Ios::SquareOnSquare : Ios::ArrowClockwise"
                    :android="$copiesProblem === null ? Android::ContentCopy : Android::Refresh"
                    tap="makeCopies"
                >
                    {{ $copiesProblem === null ? trans_choice('Make :count copy|Make :count copies', $copies) : 'Try again' }}
                </x-ui.sheet-confirm>
            </column>
        @endif
    </native:bottom-sheet>

    <native:bottom-sheet ref="item-team-move-sheet" :visible="$showTeamMove" @dismiss="closeTeamMove" detents="fit">
        @if ($showTeamMove)
            @php($moveTeams = $this->teamMoveTeams())
            @php($destination = $this->teamMoveDestination())
            <column class="w-full bg-theme-background">
                <x-ui.sheet-header ref="item-team-move-close" title="Move to another team" close="closeTeamMove" />
                <column class="w-full gap-5 px-4 pt-2">
                    <x-ui.text class="px-1">{{ $this->teamMoveSummary() }}</x-ui.text>

                    <column ref="item-team-move-teams" class="{{ Card::classes() }} w-full">
                        @foreach ($moveTeams as $id => $name)
                            <pressable
                                ref="item-team-move-team-{{ $id }}"
                                :a11y-label="$name.', '.$destination.($id === $teamMoveTeamId ? ', selected' : '')"
                                class="min-h-12 w-full justify-center px-4 py-2"
                                @tap="pickTeamMoveTeam({{ $id }})"
                            >
                                <row class="w-full items-center gap-4">
                                    <column class="h-9 w-9 items-center justify-center rounded-full bg-theme-primary">
                                        <text font="medium" class="text-base text-theme-on-primary">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</text>
                                    </column>
                                    <column class="flex-1 gap-0.5">
                                        <text class="text-lg text-theme-on-surface">{{ $name }}</text>
                                        <x-ui.text>{{ $destination }}</x-ui.text>
                                    </column>
                                    @if ($id === $teamMoveTeamId)
                                        <icon :ios="Ios::Checkmark" :android="Android::Check" :size="18" class="text-theme-primary-text" />
                                    @endif
                                </row>
                            </pressable>
                            @unless ($loop->last)
                                <divider class="ml-[68]" />
                            @endunless
                        @endforeach
                    </column>

                    @if ($teamMoveProblem !== null)
                        @php($note = $this->teamMoveNote())
                        <row ref="item-team-move-problem" class="w-full items-start gap-2.5 rounded-xl bg-theme-primary/15 p-3.5">
                            <native:icon
                                :ios="$teamMoveProblem === 'offline' ? Ios::WifiSlash : Ios::ExclamationmarkCircle"
                                :android="$teamMoveProblem === 'offline' ? Android::WifiOff : Android::ErrorOutline"
                                :size="20"
                                class="text-theme-primary-text"
                            />
                            <column class="flex-1 gap-0.5">
                                <text font="semibold" class="text-base text-theme-primary-text">{{ $note['title'] }}</text>
                                <text class="text-base text-theme-on-surface">{{ $note['message'] }}</text>
                            </column>
                        </row>
                    @endif
                </column>

                <x-ui.sheet-confirm
                    ref="item-team-move-submit"
                    :ios="$teamMoveProblem === null ? Ios::ArrowLeftArrowRight : Ios::ArrowClockwise"
                    :android="$teamMoveProblem === null ? Android::SwapHoriz : Android::Refresh"
                    tap="moveToTeam"
                >
                    {{ $teamMoveProblem === null ? 'Move to '.($moveTeams[$teamMoveTeamId] ?? 'another team') : 'Try again' }}
                </x-ui.sheet-confirm>
            </column>
        @endif
    </native:bottom-sheet>
@else
    <x-ui.not-found ref="item-missing" thing="thing" />
@endif
