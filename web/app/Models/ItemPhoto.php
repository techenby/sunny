<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ItemPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Touches(['item'])]
class ItemPhoto extends Model
{
    /** @use HasFactory<ItemPhotoFactory> */
    use HasFactory;

    public const MAX_PER_ITEM = 10;

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /** @return Attribute<string, never> */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::temporaryUrl($this->path, now()->addMinutes(30)),
        );
    }

    /** @return Attribute<string|null, never> */
    protected function thumbUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumb_path ? Storage::temporaryUrl($this->thumb_path, now()->addMinutes(30)) : null,
        );
    }
}
