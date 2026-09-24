@use('App\Icons\Android')
@use('App\Icons\Ios')

<list-item
    :headline="$item['name']"
    :supporting="$item['children_count'] > 0
        ? $item['type']->label().' · '.trans_choice(':count item|:count items', $item['children_count'])
        : $item['type']->label()"
    :leadingIconIos="$item['type']->iosIcon()"
    :leadingIconAndroid="$item['type']->androidIcon()"
    :leadingIconBgColor="$item['type']->iconColor()"
    :trailingIconIos="Ios::ChevronRight"
    :trailingIconAndroid="Android::ChevronRight"
    @navigate('/inventory/'.$item['id'], ['from' => $from])
/>
