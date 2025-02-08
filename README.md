# Sunny

This application is one that I am building for my spouse and I to manage our lives/household. I am not striving for this app to be perfect, but as a playground for me to play with new technologies, packages and approaches. You can listen to more about this side project on the [Side Project podcast](https://sideprojectpodcast.com/episodes/sunny-with-andy-newhouse).

I do hope that this repo can be used as a resource for how I approach bulding data models, Livewire components, and testing.

App Sections:
- Berries
- Inventory
- Log Pose
- Cookbook
- Collections

> [!NOTE]
> For all Entity-Relationship diagrams assume the table has Laravel timestamp columns (`created_at`, `updated_at`) unless otherwise specified.

## Berries

Tables in this section are prefixed with `berries_` i.e. `berries_subscriptions`. This makes looking at data in TablePlus more organized.

```mermaid
erDiagram
    SUBSCRIPTION {
        bigint id PK
        string name
        string frequency "Cast to the BillingFrequency enum"
        double amount
        timestamp billed_at "Cast to datetime"
        timestamp due_at "Cast to datetime"
        text notes
    }
```


## Inventory

```mermaid
erDiagram
    BIN |o--|{ LOCATION : "bin belongsTo location<br/>location hasMany bins"
    THING |o--|{ BIN : "thing belongsTo bin<br/>bin hasMany things"
    THING |o--|{ LOCATION : "thing belongsTo location<br/>location hasMany things"
    BIN {
        bigint id PK
        bigint location_id FK "nullable"
        string name
        string type "nullable"
    }
    THING {
        bigint id PK
        bigint bin_id FK "nullable"
        bigint location_id FK "nullable, Uses the location from bin, but can be overwritten"
        string name
    }
    LOCATION {
        bigint id PK
        string name
    }
```

## Log Pose

```mermaid
erDiagram
    TILE {
        bigint id PK
        string name
        string type "nullable"
        json data "nullable"
        json settings "nullable"
    }
```

The `tile` model is extended from Spatie's Dashboard Tile model which is why it's table name is `dashboard_tiles`

## Cookbook

```mermaid
erDiagram
    RECIPE ||--o{ "MEDIA†": morphMany
    RECIPE ||--o{ "TAGGABLES†": morphMany
    "TAGGABLES†" ||--|| "TAGS†": morphMany
    RECIPE {
        bigint id PK
        string name
        string slug
        string source "nullable"
        string servings "nullable"
        string prep_time "nullable"
        string cook_time "nullable"
        string total_time "nullable"
        text description "nullable"
        text ingredients "nullable"
        text instructions "nullable"
        text notes "nullable"
        text nutrution "nullable"
    }
    "MEDIA†" {
        bigint id PK
        string model_type
        bigint model_id
        uuid uuid UK "nullable"
        string collection_name
        string name
        string file_name
        string mime_type "nullable"
        string disk
        string conversions_disk "nullable"
        bigint size
        json manipulations
        json custom_properties
        json generated_conversion
        json responsive_images
        int order_column "nullable"
    }
    "TAGS†" {
        bigint id PK
        json name
        json slug
        string type "nullable"
        int order_column "nullable"
    }
    "TAGGABLES†" {
        bigint tag_id UK
        string taggable_type UK
        bigint taggable_id UK
    }
```

† The `media`, `tags` and `taggables` tables are all defined by Spatie packages. It's interesting that there are two approaches to the same time of relationship. The [laravel-tags](https://spatie.be/docs/laravel-tags/v4/introduction) package uses a pivot table, where the [laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary/v11/introduction) does not.

## Collections

### LEGO

Tables in this section are prefixed with `lego_` i.e. `lego_parts`. This makes looking at data in TablePlus more organized.

Models in this section are prefixed with `Lego` i.e. `LegoBin`. This is to differentiate the LEGO collection from the models in the Inventory section.

```mermaid
erDiagram
    PART }|--|| GROUP: "part belongsTo group<br/>group hasMany parts"
    BIN ||--|{ "BIN_PART*": "bin belongsToMany parts<br/>part belongsToMany bins<br/>`bin_part` is the pivot"
    "BIN_PART*" ||--|{ PART: ""
    BIN ||--|{ "BIN_COLOR*": "bin belongsToMany colors<br/>color belongsToMany bins<br/>`bin_color` is the pivot"
    "BIN_COLOR*" ||--|{ COLOR: ""
    PART {
        bigint id PK
        bigint group_id FK
        string name
        string part_number
        string image
        string href
    }
    COLOR {
        bigint id PK
        string name
        string hex
        bool is_trans
        json external "nullable"
    }
    GROUP {
        bigint id PK
        bigint parent_id FK "nullable, uses single table inheritance so the `parent_id` will be another group"
        string name
        string slug
        bool has_parts "default: `false`"
        string href
        string summary
        text description
    }
    BIN {
        bigint id PK
        string type
        string location "nullable"
        text notes
    }
    "BIN_COLOR*" {
        bigint bin_id
        bigint color_id
    }
    "BIN_PART*" {
        bigint bin_id
        bigint part_id
    }
```

* `bin_color` and `bin_part` are pivot tables, and do not have `id`, `created_at` or `updated_at` columns.
