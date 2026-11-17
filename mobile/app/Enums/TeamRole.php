<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Can do everything, including removing people and deleting the team.',
            self::Admin => 'Can also rename the team, change its settings and invite people.',
            self::Member => 'Can add and change recipes, inventory, routines and lists.',
        };
    }

    public function canUpdateTeam(): bool
    {
        return $this !== self::Member;
    }

    public function canInvite(): bool
    {
        return $this !== self::Member;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }

    /** @return list<self> */
    public static function assignable(): array
    {
        return [self::Member, self::Admin];
    }
}
