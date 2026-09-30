@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar title="Lists" display-mode="large" :back="false">
    <native:top-bar-action
        ref="create-list"
        id="create-list"
        label="New list"
        :ios-icon="Ios::Plus"
        :android-icon="Android::Add"
        @navigate('/lists/create')
    />
</native:top-bar>

<list ref="checklist-list" fill separator class="bg-theme-background">
    @if ($this->lists)
        <list-section :footer="trans_choice(':count list|:count lists', count($this->lists))">
            @foreach ($this->lists as $checklist)
                <list-item
                    :headline="$checklist['name']"
                    :supporting="$checklist['summary']"
                    :leadingIconIos="$checklist['type']->iosIcon()"
                    :leadingIconAndroid="$checklist['type']->androidIcon()"
                    :leadingIconBgColor="$checklist['type']->iconColor()"
                    :trailingIconIos="Ios::ChevronRight"
                    :trailingIconAndroid="Android::ChevronRight"
                    @navigate="'/lists/'.$checklist['id']"
                />
            @endforeach
        </list-section>
    @else
        <list-section>
            <list-item
                ref="lists-empty"
                headline="No lists yet"
                supporting="Tap + to start a to-do, shopping, or wish list."
                :leadingIconIos="Ios::Checklist"
                :leadingIconAndroid="Android::Checklist"
                :leadingIconColor="theme('on-surface-variant')"
            />
        </list-section>
    @endif
</list>
