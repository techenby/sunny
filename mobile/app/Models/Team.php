<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Team extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['members' => 'array'];
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone ?? config('app.timezone'))->startOfDay();
    }

    public function hasSeveralMembers(): bool
    {
        return count($this->members ?? []) > 1;
    }

    /** @return array{id: int, name: string, initials: string}|null */
    public function member(?int $id): ?array
    {
        return $id === null ? null : collect($this->members ?? [])->firstWhere('id', $id);
    }

    /** @return array{id: int, name: string, initials: string}|null */
    public function soleMember(): ?array
    {
        return count($this->members ?? []) === 1 ? $this->members[0] : null;
    }

    /** @return array{name: string, initials: string|null, whose: string|null}|null */
    public function owner(?int $userId): ?array
    {
        if (! $this->hasSeveralMembers()) {
            return null;
        }

        $member = $this->member($userId);

        return $member === null
            ? ['name' => 'Household', 'initials' => null, 'whose' => null]
            : ['name' => $member['name'], 'initials' => $member['initials'], 'whose' => Str::before($member['name'], ' ').'’s'];
    }
}
