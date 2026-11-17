<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeamInvitation */
class TeamInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'email' => $this->email,
            'role' => $this->role->value,
            'team' => $this->whenLoaded('team', fn (): array => [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'slug' => $this->team->slug,
            ]),
            'inviter' => $this->whenLoaded('inviter', fn (): array => [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
            ]),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
