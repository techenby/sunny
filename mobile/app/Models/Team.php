<?php

namespace App\Models;

use App\Enums\TeamRole;
use App\Http\Integrations\Sunny\SunnyStore;
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
        return [
            'is_personal' => 'boolean',
            'week_start' => 'integer',
            'members' => 'array',
            'invitations' => 'array',
            'invitations_fetched_at' => 'immutable_datetime',
        ];
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

    /** @return list<array{id: int, name: string, initials: string, role: TeamRole}> */
    public function memberList(): array
    {
        return collect($this->members ?? [])
            ->map(fn (array $member): array => [...$member, 'role' => TeamRole::tryFrom($member['role'] ?? '') ?? TeamRole::Member])
            ->all();
    }

    public function roleOf(?int $userId): ?TeamRole
    {
        return TeamRole::tryFrom($this->member($userId)['role'] ?? '');
    }

    public function currentRole(): ?TeamRole
    {
        return $this->roleOf(app(SunnyStore::class)->userId());
    }

    /** @return list<array{code: string, email: string, role: TeamRole, expires_at: string|null}> */
    public function invitationList(): array
    {
        return collect($this->invitations ?? [])
            ->filter(fn (mixed $invitation): bool => is_array($invitation) && is_string($invitation['code'] ?? null) && is_string($invitation['email'] ?? null))
            ->map(fn (array $invitation): array => [
                'code' => $invitation['code'],
                'email' => $invitation['email'],
                'role' => TeamRole::tryFrom($invitation['role'] ?? '') ?? TeamRole::Member,
                'expires_at' => is_string($invitation['expires_at'] ?? null) ? $invitation['expires_at'] : null,
            ])
            ->values()
            ->all();
    }
}
