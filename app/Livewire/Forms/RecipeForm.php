<?php

namespace App\Livewire\Forms;

use App\Models\Recipe;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\Validate;
use Livewire\Form;

class RecipeForm extends Form
{
    public ?string $name;
    public ?string $source;
    public $image;
    public ?string $servings;
    public ?string $prep_time;
    public ?string $cook_time;
    public ?string $total_time;
    public ?string $description;
    public ?string $ingredients;
    public ?string $instructions;
    public ?string $notes;
    public ?string $nutrution;
    public ?Recipe $recipe;

    public function set(Recipe $recipe): void
    {
        $this->recipe = $recipe;
        $this->name = $recipe->name;
        $this->source = $recipe->source;
        $this->image = $recipe->getFirstMedia('thumb');
        $this->prep_time = $recipe->prep_time;
        $this->cook_time = $recipe->cook_time;
        $this->total_time = $recipe->total_time;
        $this->description = $recipe->description;
        $this->ingredients = $recipe->ingredients;
        $this->instructions = $recipe->instructions;
        $this->notes = $recipe->notes;
        $this->nutrution = $recipe->nutrution;
    }

    public function create(): Recipe
    {
        $validated = $this->validate();

        $recipe = Recipe::create(Arr::except($validated, ['image']));

        $recipe->addMedia($this->image)->toMediaCollection('thumb');

        return $recipe;
    }

    public function update(): void
    {
        $validated = $this->validate();

        $this->image = $this->recipe->addMedia($this->image)->toMediaCollection('thumb');

        $this->recipe->update(Arr::except($validated, ['image']));
    }

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string'],
            'servings' => ['nullable', 'string'],
            'image' => ['nullable', File::image()],
            'prep_time' => ['nullable', 'string'],
            'cook_time' => ['nullable', 'string'],
            'total_time' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'ingredients' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'nutrution' => ['nullable', 'string'],
        ];
    }
}
