<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Recipe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:500'],
            'servings' => ['nullable', 'string', 'max:50'],
            'prep_time' => ['nullable', 'string', 'max:50'],
            'cook_time' => ['nullable', 'string', 'max:50'],
            'total_time' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'nutrition' => ['nullable', 'string'],
            'tags' => ['nullable', 'array', 'list'],
            'tags.*' => ['string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:10240'],
            'parent_id' => ['nullable', 'integer', Rule::exists('recipes', 'id')->where('team_id', $this->route('team')->id)],
        ];
    }
}
