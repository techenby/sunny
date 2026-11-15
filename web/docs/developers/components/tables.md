---
title: Table Components
description: Shared Blade components for list tables, such as Recipes and Inventory.
order: 7
---

# Table Components

List tables in SunnyHome are Flux tables with a small set of shared Blade
components on top. They live in `resources/views/components/ui/table` and are
used as `<x-ui.table.*>`. The Recipes list and both Inventory tables use them.

Use them when each row stands for one record that a person can open. A table of
plain values, such as the costs page or the API tokens list, only needs
`<flux:table>`.

| Component | Purpose |
| --- | --- |
| `<x-ui.table.row>` | A row that opens its record when clicked anywhere. |
| `<x-ui.table.name>` | The record's name, as the row's real link or button. |
| `<x-ui.table.thumb>` | A 36px photo or icon tile beside the name. |
| `<x-ui.table.blank>` | A faint dash for a missing value. |
| `<x-ui.table.actions>` | The row's "⋯" menu. |
| `<x-ui.table.money-cell>` | A right-aligned cell that formats cents as dollars. |

## A complete row

```blade
<flux:table :paginate="$this->recipes" class="w-full">
    <flux:table.columns>
        <flux:table.column>{{ __('Name') }}</flux:table.column>
        <flux:table.column class="w-35">{{ __('Time') }}</flux:table.column>
        <flux:table.column class="w-12" align="end"></flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @foreach ($this->recipes as $recipe)
            <x-ui.table.row :key="$recipe->id" :href="route('recipes.show', $recipe)">
                <flux:table.cell>
                    <x-ui.table.name :href="route('recipes.show', $recipe)">
                        <x-slot:thumb>
                            <x-ui.table.thumb :src="$recipe->thumb_url" icon="book-open" />
                        </x-slot:thumb>

                        {{ $recipe->name }}
                    </x-ui.table.name>
                </flux:table.cell>
                <flux:table.cell>
                    @if ($recipe->total_time)
                        {{ $recipe->total_time }}
                    @else
                        <x-ui.table.blank />
                    @endif
                </flux:table.cell>
                <flux:table.cell align="end">
                    <x-ui.table.actions :label="__('Actions for :name', ['name' => $recipe->name])">
                        <flux:menu.item :href="route('recipes.edit', $recipe)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                    </x-ui.table.actions>
                </flux:table.cell>
            </x-ui.table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
```

Flux tables already use a fixed layout. Add `class="w-full"` to the table and
fixed widths to every column except the name, so the columns stay the same
width from page to page and long names truncate.

## Row

`<x-ui.table.row>` wraps `<flux:table.row>`. A click anywhere in the row opens
the record, the row gets a soft background on hover and keyboard focus, and its
cells get hairline dividers and the padding that makes a row with a thumbnail
57px tall.

| Prop | Default | Description |
| --- | --- | --- |
| `key` | `null` | The row's `wire:key`, passed on to the Flux row. |
| `href` | `null` | Where a click goes. Uses `Livewire.navigate`; a Cmd- or Ctrl-click opens a new tab. |
| `action` | `null` | A JavaScript expression to run on click when there is no `href`, such as `$wire.navigateDown(3)`. |

Give the row either an `href` or an `action`. When both are set, `href` wins,
which lets a page switch between them:

```blade
<x-ui.table.row
    :key="$item->id"
    :href="filled($search) ? route('inventory.show', ['item' => $item]) : null"
    action="$wire.navigateDown({{ $item->id }})"
>
```

Clicks on links, buttons, inputs, labels, Flux checkboxes, dialogs, and
popovers inside the row are left alone, so a row menu, a checkbox, or an
outside link never also opens the record.

The row's click handler is a convenience for the mouse. Always put an
`<x-ui.table.name>` in the row as well, so the record can be reached with the
keyboard and by screen readers.

## Name

`<x-ui.table.name>` shows the record's name in dark, medium-weight text that
turns the accent color when the row is hovered or the name has keyboard focus.
Long names truncate with an ellipsis.

| Prop | Default | Description |
| --- | --- | --- |
| `href` | `null` | With an `href`, the name is a link that uses `wire:navigate`. Without one, it is a `<button>`. |

| Slot | Description |
| --- | --- |
| default | The name. |
| `thumb` | Optional. Shown before the name, usually an `<x-ui.table.thumb>`. |

Extra attributes go on the link or button, so a button takes its action
directly:

```blade
<x-ui.table.name wire:click="navigateDown({{ $item->id }})">
    {{ $item->truncated_name }}
</x-ui.table.name>
```

The name is rendered inside a plain `<span>`, with no attributes and no
surrounding whitespace. Tests rely on this to find a row by name, for example
`assertSeeHtml('<span>Extension Cord</span>')`.

## Thumb

`<x-ui.table.thumb>` is a 36px tile with 8px corners.

| Prop | Default | Description |
| --- | --- | --- |
| `icon` | required | Heroicon name for the tile when there is no photo. |
| `src` | `null` | Photo URL. When set, the photo is shown and the icon is not. |
| `color` | `null` | A Flux color name, such as `amber`. Tints the icon tile. |

It renders one of three things:

1. The photo, when `src` is set.
2. A tinted icon tile, when there is no photo and `color` is set. Inventory
   uses this to keep its place, container, and thing colors:
   `:color="$item->type->getIconColor()"`.
3. A quiet cream tile with a faint outline icon, when neither is set. Recipes
   uses this.

## Blank

`<x-ui.table.blank />` renders a faint "—" for a missing value. It takes no
props and is hidden from screen readers. Use it instead of typing a dash, so
empty values stay quiet in every table.

## Actions

`<x-ui.table.actions>` is a dropdown with a ghost "⋯" button, 32px square,
aligned to the end of the row. Its slot holds the `<flux:menu.item>` entries.

| Prop | Default | Description |
| --- | --- | --- |
| `label` | `Actions` | The button's accessible name. Include the record's name, such as "Actions for Banana Bread". |

Put it in a cell with `align="end"`, in a column with `class="w-12"`.

## Money cell

`<x-ui.table.money-cell>` is a whole `<flux:table.cell>`, right-aligned with
tabular numbers, that formats an amount in cents as dollars.

| Prop | Default | Description |
| --- | --- | --- |
| `cents` | `0` | The amount in cents. `1250` renders as `$12.50`. |

Other attributes, such as `variant="strong"`, are passed on to the Flux cell.

```blade
<x-ui.table.money-cell :cents="$totalCents" variant="strong" />
```
