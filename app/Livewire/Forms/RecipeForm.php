<?php

namespace App\Livewire\Forms;

use App\Models\Recipe;
use Livewire\Attributes\Validate;
use Livewire\Form;

class RecipeForm extends Form
{
    public string $name;
    public ?string $source;
    public ?string $servings;
    public ?string $prep_time;
    public ?string $cook_time;
    public ?string $total_time;
    public ?string $description;
    public ?string $ingredients;
    public ?string $instructions;
    public ?string $notes;
    public ?string $nutrution;

    public function set(Recipe $recipe): void
    {
        $this->name = $recipe->name;
        $this->source = $recipe->source;
        $this->prep_time = $recipe->prep_time;
        $this->cook_time = $recipe->cook_time;
        $this->total_time = $recipe->total_time;
        $this->description = $recipe->description;
        $this->ingredients = $recipe->ingredients;
        $this->instructions = $recipe->instructions;
        $this->notes = $recipe->notes;
        $this->nutrution = $recipe->nutrution;
    }
}
