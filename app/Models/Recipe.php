<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Recipe extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\RecipeFactory> */
    use HasFactory;
    use HasTags;
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

    protected function categories(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tags->implode('name', ', '),
        );
    }

    protected function shortenedSource(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (str_contains($this->source, 'http')) {
                    return str($this->source)
                        ->after('//') // after http or https
                        ->before('/') // before  slug
                        ->when(substr_count($this->source, '.') > 1, fn ($str) => $str->after('.')) // after subdomains
                        ->when(str_contains($this->source, '?'), fn ($str) => $str->before('?')) // after subdomains
                        ->toString();
                }

                return $this->source;
            },
        );
    }
}
