<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Crew extends Model
{
    /** @use HasFactory<\Database\Factories\CrewFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
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

    /** @return HasMany<CrewInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(CrewInvitation::class);
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
        $this->owner()->where('current_crew_id', $this->id)
            ->update(['current_crew_id' => null]);

        $this->users()->where('current_crew_id', $this->id)
            ->update(['current_crew_id' => null]);

        $this->users()->detach();

        $this->delete();
    }
}
