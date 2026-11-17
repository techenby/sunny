<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CopyRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('copy', $this->route('recipe'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => [
                'required',
                'integer',
                Rule::notIn([$this->team()->id]),
                Rule::exists('team_members', 'team_id')->where('user_id', $this->user()->id),
            ],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    private function team(): Team
    {
        $team = $this->route('team');

        throw_unless($team instanceof Team, ModelNotFoundException::class);

        return $team;
    }
}
