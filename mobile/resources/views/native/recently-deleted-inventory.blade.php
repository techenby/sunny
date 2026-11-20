@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="Recently deleted" back />

@if ($this->items === [])
    <column ref="deleted-empty" fill center class="gap-3 bg-theme-background px-6">
        <column class="h-[72] w-[72] items-center justify-center rounded-full bg-theme-primary/15">
            <icon :ios="Ios::Trash" :android="Android::DeleteOutline" :size="32" class="text-theme-primary-text" />
        </column>
        <x-ui.heading size="lg" class="text-center">Nothing deleted</x-ui.heading>
        <x-ui.text class="text-center">Everything’s been restored or deleted forever. Things you delete will wait here until you decide.</x-ui.text>
    </column>
@else
    <scroll-view ref="deleted-list" fill class="bg-theme-background">
        <column class="w-full gap-3 px-4 pt-2 pb-8">
            <x-ui.text class="px-1">Nothing here goes away on its own.</x-ui.text>

            @if ($error !== '')
                <text ref="deleted-error" class="text-base text-theme-destructive">{{ $error }}</text>
            @endif

            <column class="{{ Card::classes() }} w-full">
                @foreach ($this->items as $item)
                    <column ref="deleted-item-{{ $item['id'] }}" class="w-full gap-3 px-4 py-3">
                        <row class="w-full items-start gap-4">
                            <x-ui.avatar
                                size="lg"
                                :src="$item['photo']"
                                :alt="'Photo of '.$item['name']"
                                :color="$item['type']->color()"
                                :ios="$item['type']->iosIcon()"
                                :android="$item['type']->androidIcon()"
                            />
                            <column class="flex-1 gap-0.5">
                                <x-ui.heading size="lg">{{ $item['name'] }}</x-ui.heading>
                                <x-ui.text>{{ $item['supporting'] }}</x-ui.text>
                                <x-ui.text size="sm">{{ $item['deleted'] }}</x-ui.text>
                            </column>
                        </row>
                        @if ($item['emptied'])
                            <row class="w-full items-start gap-2 rounded-xl bg-theme-surface-variant px-3 py-2">
                                <icon :ios="Ios::InfoCircle" :android="Android::Info" :size="16" class="text-theme-on-surface-variant" />
                                <x-ui.text size="sm" class="flex-1">Anything inside went to “Not in a place yet”, so it comes back empty.</x-ui.text>
                            </row>
                        @endif
                        <column class="w-full gap-2">
                            <x-ui.button
                                ref="deleted-restore-{{ $item['id'] }}"
                                variant="tint"
                                class="w-full"
                                :ios="Ios::ArrowUturnBackward"
                                :android="Android::Undo"
                                :a11y-label="'Restore '.$item['name']"
                                tap="restore({{ $item['id'] }})"
                            >Restore</x-ui.button>
                            <pressable
                                ref="deleted-forever-{{ $item['id'] }}"
                                a11y-label="Delete {{ $item['name'] }} forever"
                                class="min-h-12 w-full items-center justify-center rounded-full border border-theme-outline px-4"
                                @tap="confirmDeleteForever({{ $item['id'] }})"
                            >
                                <text font="semibold" class="text-base text-theme-destructive">Delete forever</text>
                            </pressable>
                        </column>
                    </column>
                    @unless ($loop->last)
                        <divider class="ml-[80]" />
                    @endunless
                @endforeach
            </column>

            <row class="w-full items-start gap-2 px-1">
                <icon :ios="Ios::Mappin" :android="Android::Place" :size="16" class="text-theme-primary-text" />
                <x-ui.text size="sm" class="flex-1">Restore puts it back where it was. If that place was deleted too, it comes back under “Not in a place yet”.</x-ui.text>
            </row>
        </column>
    </scroll-view>
@endif
