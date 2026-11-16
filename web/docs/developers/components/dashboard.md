---
title: Dashboard Components
description: Shared Blade components that make up the dashboard cards.
order: 8
---

# Dashboard Components

The dashboard at `/{team}/dashboard` is built from five Livewire components,
one per card, in `resources/views/components/dashboard`:

| Livewire component | Card |
| --- | --- |
| `dashboard.events` | Coming up |
| `dashboard.routines` | Today's routines |
| `dashboard.lists` | Open lists |
| `dashboard.recipes` | Recently added recipes |
| `dashboard.items` | Recently added items |

Each card is assembled from the shared Blade components on this page, used as
`<x-dashboard.*>`. The marketing page's dashboard preview
(`components/welcome/dashboard-preview.blade.php`) reuses the same components
with sample data, so a change to one shows up in both places.

## Layout rules

- Coming up is full width. The other four cards sit in one two-column grid
  with `items-start`, so each card takes its own height.
- Every card keeps its header when it has no data, and shows
  `<x-dashboard.empty>` in its body.
- Grouped cards cap what they show and say what is left over with
  `<x-dashboard.more>`: three lists of three items, and three routines.
  Apply caps in the query, not by hiding rows with CSS.
- Recipes and items show five rows each.
- Relative times are written in full, such as "3 days ago".

## Card

`<x-dashboard.card>` is a Flux card with a header and a flush body.

| Prop | Default | Description |
| --- | --- | --- |
| `heading` | required | The card title. |
| `action` | `null` | Label for the header link, such as "All lists". Shown with a trailing arrow. |
| `href` | `null` | Where the header link goes. |

```blade
<x-dashboard.card :heading="__('Open lists')" :action="__('All lists')" :href="route('lists.index')">
    ...
</x-dashboard.card>
```

## Empty

`<x-dashboard.empty>` is the one empty state for every card: a 40px icon tile,
a title, one muted line, and an optional accent link.

| Prop | Default | Description |
| --- | --- | --- |
| `icon` | required | Icon name for the tile. |
| `heading` | required | Short title, such as "No recipes yet". |
| `text` | required | One line of explanation. |
| `action` | `null` | Label for the link, such as "Add a recipe". |
| `href` | `null` | Where the link goes. |
| `tile` | neutral zinc | Classes for the tile's background and icon color. |
| slot | empty | Extra actions shown beside `action`, such as a second link. |

```blade
<x-dashboard.empty
    icon="book-open"
    tile="bg-amber-200 text-amber-800 dark:bg-amber-400/40 dark:text-amber-200"
    :heading="__('No recipes yet')"
    :text="__('Save one from a link or type it in, and the newest show up here.')"
    :action="__('Add a recipe')"
    :href="route('recipes.create')"
/>
```

Recipes use an amber tile and items an orange one. Lists and routines use the
default.

## More

`<x-dashboard.more>` is a footer with a hairline top border, a link on the
left, and an optional muted note on the right.

| Prop | Default | Description |
| --- | --- | --- |
| `href` | required | Where the link goes. |
| `label` | required | Link text, such as "2 more lists". |
| `note` | `null` | Muted text on the right, such as "Showing 3 of 5 open lists". |

Show it only when something is left over. Coming up also uses it for the
"Connect a calendar" link when a household has no calendars.

## Row

`<x-dashboard.row>` is one linked row in the recipes and items cards: a 32px
tile, a name, a secondary line, and a time.

| Prop | Default | Description |
| --- | --- | --- |
| `href` | required | Where the row goes. |
| `heading` | required | The name. |
| `icon` | required | Icon for the tile when there is no photo. |
| `tile` | required | Classes for the tile's background and icon color. |
| `src` | `null` | Photo URL. Shown in place of the icon. |
| `subheading` | `null` | Secondary line, such as "in Left Closet". |
| `meta` | `null` | Text at the end of the row, usually a relative time. |

Put rows in a `<flux:card.bleed>` with dividers, so they run edge to edge:

```blade
<flux:card.bleed class="divide-y divide-zinc-900/5 dark:divide-white/10">
    @foreach ($this->items as $item)
        <x-dashboard.row
            wire:key="dashboard-item-{{ $item->id }}"
            :href="route('inventory.show', $item)"
            :heading="$item->truncated_name"
            :subheading="$item->parent ? __('in :parent', ['parent' => $item->parent->name]) : null"
            :meta="$item->created_at->diffForHumans()"
            :src="$item->thumb_url"
            :icon="$item->type->getIcon()"
            tile="bg-orange-100 text-orange-700 dark:bg-orange-400/40 dark:text-orange-200"
        />
    @endforeach
</flux:card.bleed>
```

## Day and event

`<x-dashboard.day>` is one column in Coming up: a small heading with that
day's events in its slot.

| Prop | Default | Description |
| --- | --- | --- |
| `label` | required | The column heading, such as "Today". |

`<x-dashboard.event>` is one event, with a colored bar on its left edge. The
time sits on its own line above the title. A long title wraps and a long
location truncates.

| Prop | Default | Description |
| --- | --- | --- |
| `time` | required | The time range, or "All day". |
| `title` | required | The event title. |
| `color` | required | CSS color for the left bar, usually the calendar feed's color. |
| `location` | `null` | Shown under the title. |
| `declined` | `false` | Strikes the event through and fades it. |

Coming up always shows three day columns. A day with no events says "Nothing
scheduled." The card is lazy-loaded, and its placeholder shows the header and
the three day headings while events load.

## List, routine, and check item

`<x-dashboard.list>` is one list in Open lists: a type icon, the list name as
a link, and an optional "Household" badge. Its slot holds the items.

| Prop | Default | Description |
| --- | --- | --- |
| `name` | required | The list name. |
| `icon` | required | Icon for the list type. |
| `color` | required | Flux color for the icon, from `ChecklistType::getIconColor()`. |
| `href` | `null` | Where the name links. |
| `household` | `false` | Shows the "Household" badge. |

`<x-dashboard.routine>` is one routine in Today's routines: a time-of-day
icon, the name, a count such as "2/5", and a progress bar. Its slot holds the
steps as `<li>` elements; the component supplies the `<ul>`.

| Prop | Default | Description |
| --- | --- | --- |
| `name` | required | The routine name. |
| `icon` | required | Icon for the time of day. |
| `completed` | required | Number of completed steps. |
| `total` | required | Total number of steps. |
| `household` | `false` | Shows the "Household" badge. |

`<x-dashboard.check-item>` is one checkable line in either card. It renders a
button with `role="checkbox"`, so put the toggle action on it.

| Prop | Default | Description |
| --- | --- | --- |
| `completed` | `false` | Shows the item as checked and struck through. |

```blade
<x-dashboard.routine
    :name="$occurrence->routine->name"
    :icon="$occurrence->routine->time_of_day->getIcon()"
    :completed="$completedCount"
    :total="$occurrence->steps->count()"
>
    @foreach ($occurrence->steps as $step)
        <li wire:key="dashboard-occurrence-step-{{ $step->id }}">
            <x-dashboard.check-item :completed="$step->isCompleted()" wire:click="toggle({{ $step->id }})">
                {{ $step->step?->name }}
            </x-dashboard.check-item>
        </li>
    @endforeach
</x-dashboard.routine>
```
