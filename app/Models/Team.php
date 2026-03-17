<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasSlug;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;
    use HasSlug;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
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

    public function hasUser(User $user): bool
    {
        return $this->users->contains($user) || $this->user_id === $user->id;
    }

    public function removeUser(User $user): void
    {
        $this->users()->detach($user);
    }

    public function purge(): void
    {
        $this->owner()->where('current_team_id', $this->id)
            ->update(['current_team_id' => null]);

        $this->users()->where('current_team_id', $this->id)
            ->update(['current_team_id' => null]);

        $this->users()->detach();

        $this->recipes()->delete();

        $this->delete();
    }
}
