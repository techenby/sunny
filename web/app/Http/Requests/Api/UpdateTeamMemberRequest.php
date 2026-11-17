<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateMember', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
        ];
    }
}
