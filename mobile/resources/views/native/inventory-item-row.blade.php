@use('App\NativeComponents\Inventory')

{{-- One inventory entry in a card. Expects $item, $ref and $from, and
     optionally $supporting to replace the "what's inside" summary and $last. --}}

<x-ui.row
    :ref="$ref"
    :url="'/inventory/'.$item['id']"
    :from="$from"
    :a11y-label="$item['name']"
    :headline="$item['name']"
    :supporting="$supporting ?? Inventory::summary($item)"
    :src="$item['thumb_path'] ?? $item['photo_url'] ?? null"
    :alt="'Photo of '.$item['name']"
    :avatar-color="$item['type']->color()"
    :ios="$item['type']->iosIcon()"
    :android="$item['type']->androidIcon()"
    trailing="chevron"
    top
    :last="$last ?? true"
/>
