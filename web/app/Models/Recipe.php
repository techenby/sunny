<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RecipeFactory;
use DOMDocument;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function ($recipe) {
            $recipe->slug = $recipe->generateUniqueSlug($recipe->name);
        });

        static::updating(function ($recipe) {
            if ($recipe->isDirty('name')) {
                $recipe->slug = $recipe->generateUniqueSlug($recipe->name);
            }
        });
    }

    public function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        $existingSlugs = self::query()
            ->where('team_id', $this->team_id)
            ->where('slug', 'like', $baseSlug . '%')
            ->when($this->exists, fn ($query) => $query->where('id', '!=', $this->id))
            ->pluck('slug');

        if ($existingSlugs->doesntContain($baseSlug)) {
            return $baseSlug;
        }

        $counter = 1;

        while ($existingSlugs->contains($baseSlug . '-' . $counter)) {
            $counter++;
        }

        return $baseSlug . '-' . $counter;
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Recipe, $this> */
    public function remixes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function shortenedSource(): ?string
    {
        if (! $this->source) {
            return null;
        }

        if ($this->isSourceUrl()) {
            $host = parse_url($this->source, PHP_URL_HOST);

            return str_replace('www.', '', $host ?? $this->source);
        }

        return Str::limit($this->source, 30);
    }

    public function isSourceUrl(): bool
    {
        return filter_var($this->source, FILTER_VALIDATE_URL) !== false;
    }

    public function enableSharing(): void
    {
        $this->update(['share_token' => Str::uuid()->toString()]);
    }

    public function disableSharing(): void
    {
        $this->update(['share_token' => null]);
    }

    public function isShared(): bool
    {
        return ! is_null($this->share_token);
    }

    /** @return HasMany<MealPlanItem, $this> */
    public function mealPlanItems(): HasMany
    {
        return $this->hasMany(MealPlanItem::class);
    }

    /**
     * The most recent day before today, in the team's timezone, that this
     * recipe was on the meal plan.
     */
    public function lastMadeOn(): ?CarbonImmutable
    {
        $date = $this->mealPlanItems()
            ->join('meal_plan_slots', 'meal_plan_slots.id', '=', 'meal_plan_items.meal_plan_slot_id')
            ->where('meal_plan_slots.date', '<', $this->team->today()->toDateString())
            ->max('meal_plan_slots.date');

        return $date ? CarbonImmutable::parse($date) : null;
    }

    /**
     * The ingredients as plain text, one entry per line. The editor stores
     * them as an HTML list, but older or imported recipes may use paragraphs
     * or line breaks instead.
     *
     * @return list<string>
     */
    public function ingredientLines(): array
    {
        if (blank($this->ingredients)) {
            return [];
        }

        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8"><body>' . $this->ingredients . '</body>', LIBXML_NOERROR);

        $listItems = $document->getElementsByTagName('li');

        if ($listItems->length > 0) {
            $lines = [];

            foreach ($listItems as $listItem) {
                // A nested list's items are lines of their own.
                $text = '';
                foreach ($listItem->childNodes as $child) {
                    if (! in_array($child->nodeName, ['ul', 'ol'], true)) {
                        $text .= $child->textContent;
                    }
                }

                $lines[] = $text;
            }
        } else {
            $text = preg_replace('/<br\s*\/?>|<\/(p|div|h\d)>/i', "\n", $this->ingredients);
            $lines = preg_split('/\r\n|\r|\n/', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5)) ?: [];
        }

        $lines = array_map(fn (string $line): string => trim((string) preg_replace('/\s+/u', ' ', $line)), $lines);

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo_path ? Storage::temporaryUrl($this->photo_path, now()->addMinutes(30)) : null,
        );
    }

    protected function thumbUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumb_path ? Storage::temporaryUrl($this->thumb_path, now()->addMinutes(30)) : $this->photo_url,
        );
    }
}
