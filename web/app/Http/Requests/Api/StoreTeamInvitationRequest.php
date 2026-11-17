<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Rules\UniqueTeamInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('inviteMember', $this->team());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueTeamInvitation($this->team())],
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
        ];
    }

    private function team(): Team
    {
        $team = $this->route('team');

        throw_unless($team instanceof Team, ModelNotFoundException::class);

        return $team;
    }
}
