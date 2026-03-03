<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Recipes;

use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class RecipeForm extends Form
{
    public ?Recipe $editingRecipe = null;

    public string $name = '';

    public ?string $source = '';

    public ?string $servings = '';

    public ?string $prep_time = '';

    public ?string $cook_time = '';

    public ?string $total_time = '';

    public ?string $description = '';

    public ?string $ingredients = '';

    public ?string $instructions = '';

    public ?string $notes = '';

    public ?string $nutrition = '';

    public ?int $parent_id = null;

    public function load(Recipe $recipe): void
    {
        $this->fill([
            'editingRecipe' => $recipe,
            'name' => $recipe->name,
            'source' => $recipe->source,
            'servings' => $recipe->servings,
            'prep_time' => $recipe->prep_time,
            'cook_time' => $recipe->cook_time,
            'total_time' => $recipe->total_time,
            'description' => $recipe->description,
            'ingredients' => $recipe->ingredients,
            'instructions' => $recipe->instructions,
            'notes' => $recipe->notes,
            'nutrition' => $recipe->nutrition,
            'parent_id' => $recipe->parent_id,
        ]);
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingRecipe) {
            $this->editingRecipe->update($data);
        } else {
            Auth::user()->currentTeam->recipes()->create($data);
        }

        $this->reset();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
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
            'parent_id' => ['nullable', 'integer', 'exists:recipes,id'],
        ];
    }
}
