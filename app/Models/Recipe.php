<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Recipe extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\RecipeFactory> */
    use HasFactory;
    use InteractsWithMedia;

    public $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Recipe $recipe) {
            $recipe->slug = str($recipe->name)->slug()->toString();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumb')
            ->singleFile();
    }

    protected function shortenedSource(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (str_contains($this->source, 'http')) {
                    return str($this->source)->after('//')->before('/')->after('.')->toString();
                }

                return $this->source;
            },
        );
    }
}
