<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Recipe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveSharedRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Recipe::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('team_members', 'team_id')->where('user_id', $this->user()->id)],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
