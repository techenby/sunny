---
title: Inventory
description: Organize household belongings by location, bin, and item.
order: 4
---

# Inventory

Inventory helps a team record where household belongings are stored. Items can
be nested inside other entries, creating paths such as **Garage → Blue Bin →
Extension Cord**.

## Choose an item type

Every entry has one of three types:

- **Location** for a room, closet, garage, shelf, or other place.
- **Bin** for a box, tote, drawer, or container.
- **Item** for the object being stored.

The type supplies a recognizable icon and can be used as a filter. The
**Parent** field determines where the entry appears in the hierarchy.

## Add an inventory entry

1. Open **Inventory** from the main sidebar.
2. Navigate into the location or container where the entry belongs, if needed.
3. Select **Add Item**.
4. Enter a name and choose its type.
5. Confirm the parent, or choose **None** to place it at the top level.
6. Optionally add a JPG or PNG photo up to 5 MB.
7. Add any custom metadata fields you want, such as serial number, purchase
   date, size, or warranty information.
8. Select **Create**.

Select an entry's name to open it. Use the breadcrumbs or back button to move
through the inventory hierarchy.

## Search, filter, and update inventory

Use the search field to find entries by name. The filter menu can limit results
to Locations, Bins, or Items and can show deleted entries.

An entry's menu provides these actions:

- **Edit** changes its name, photo, type, parent, or metadata.
- **QR Code** creates a scannable link back to the entry.
- **Duplicate** creates up to 25 copies, useful for similar containers or
  repeated household items.
- **Move to Team** transfers the entry to another team you belong to.
- **Delete** moves the entry into the deleted-items view.

Select multiple entries to change their parent or delete them together. In the
deleted-items view, use **Restore** to return an entry or **Delete Forever** to
remove it permanently.

> [!WARNING]
> **Delete Forever** cannot be undone.

## Import Amazon order history

Sunny can turn an Amazon Order History CSV export into inventory items.

1. Navigate to the location or bin where the imported items should be placed.
2. Open the menu beside **Add Item** and select **Import**.
3. Upload the Amazon Order History CSV or TXT file.
4. Optionally filter out gifts, consumables, or orders outside a date range.
5. Select **Import**.

Imported entries include the purchase amount, ASIN, order date, and Amazon
website as metadata when those values are present in the export. Sunny reports
how many rows were imported and skipped.
