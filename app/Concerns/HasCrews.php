<?php

namespace App\Concerns;

use App\Models\Crew;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

trait HasCrews
{
    /** @return HasMany<Crew, $this> */
    public function ownedCrews(): HasMany
    {
        return $this->hasMany(Crew::class, 'user_id');
    }

    /** @return BelongsToMany<Crew, $this> */
    public function crews(): BelongsToMany
    {
        return $this->belongsToMany(Crew::class)->withTimestamps();
    }

    /** @return Collection<int, Crew> */
    public function allCrews(): Collection
    {
        return $this->ownedCrews->merge($this->crews);
    }

    /** @return BelongsTo<Crew, $this> */
    public function currentCrew(): BelongsTo
    {
        return $this->belongsTo(Crew::class, 'current_crew_id');
    }

    public function ownsCrew(Crew $crew): bool
    {
        return $this->id === $crew->user_id;
    }

    public function belongsToCrew(Crew $crew): bool
    {
        return $this->ownsCrew($crew) || $this->crews->contains($crew);
    }

    public function isCurrentCrew(Crew $crew): bool
    {
        return $this->current_crew_id === $crew->id;
    }

    public function addCrew(string $name): Crew
    {
        $crew = $this->ownedCrews()->create([
            'name' => $name,
        ]);

        $this->switchCrew($crew);

        return $crew;
    }

    public function switchCrew(Crew $crew): bool
    {
        if (! $this->belongsToCrew($crew)) {
            return false;
        }

        $this->forceFill(['current_crew_id' => $crew->id])->save();

        $this->setRelation('currentCrew', $crew);

        return true;
    }
}
