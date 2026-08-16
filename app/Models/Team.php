<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\Appearance;
use App\Enums\TeamRole;
use Carbon\CarbonImmutable;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'is_personal', 'address', 'timezone', 'week_start', 'appearance', 'rotation'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (blank($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /** @return HasMany<CalendarFeed, $this> */
    public function calendarFeeds(): HasMany
    {
        return $this->hasMany(CalendarFeed::class);
    }

    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /** @return BelongsToMany<User, $this, Pivot> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<TeamInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** @return HasMany<Recipe, $this> */
    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    /** @return HasMany<Checklist, $this> */
    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    /** @return HasMany<Routine, $this> */
    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    /**
     * The only member of the household, or null once there is more than one.
     * A one-person team has nobody to share with, so new routines and lists
     * default to them rather than to the household.
     */
    public function soleMember(): ?User
    {
        $members = $this->members()->take(2)->get();

        return $members->count() === 1 ? $members->first() : null;
    }

    /**
     * Today's calendar date where the team actually lives, which is what
     * decides whether a routine is due.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone)->startOfDay();
    }

    public function purge(): void
    {
        $this->members()->where('current_team_id', $this->id)
            ->update(['current_team_id' => null]);

        $this->members()->detach();

        $this->recipes()->delete();

        $this->delete();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'address' => 'array',
            'appearance' => Appearance::class,
        ];
    }
}
