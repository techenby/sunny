<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Recipes;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\UpdateRecipe;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class RecipeForm extends Form
{
    public ?Recipe $editingRecipe = null;

    public string $name = '';

    /** @var string[] */
    public array $tags = [];

    public ?string $source = null;

    public ?string $servings = null;

    public ?string $prep_time = null;

    public ?string $cook_time = null;

    public ?string $total_time = null;

    public ?string $description = null;

    public ?string $ingredients = null;

    public ?string $instructions = null;

    public ?string $notes = null;

    public ?string $nutrition = null;

    public ?TemporaryUploadedFile $photo = null;

    public ?string $existingPhotoUrl = null;

    public bool $removePhoto = false;

    public ?int $parent_id = null;

    public function load(Recipe $recipe): void
    {
        $this->fill([
            'editingRecipe' => $recipe,
            'name' => $recipe->name,
            'tags' => $recipe->tags ?? [],
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
            'existingPhotoUrl' => $recipe->photo_path ? Storage::temporaryUrl($recipe->photo_path, now()->addMinutes(30)) : null,
        ]);
    }

    public function save(): void
    {
        $this->validate();

        $data = $this->except(['editingRecipe', 'parent_id', 'existingPhotoUrl', 'removePhoto']);

        if ($this->editingRecipe) {
            (new UpdateRecipe)->handle($this->editingRecipe, $data, $this->removePhoto);
        } else {
            (new CreateRecipe)->handle(Auth::user()->currentTeam, $data);
        }

        $this->reset();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
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
            'photo' => ['nullable', 'image', 'max:5120'],
            'parent_id' => ['nullable', 'integer', 'exists:recipes,id'],
        ];
    }
}
