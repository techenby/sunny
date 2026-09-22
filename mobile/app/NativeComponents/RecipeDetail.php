<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class RecipeDetail extends NativeComponent
{
    /**
     * @return array{id: int, name: string, course: string, servings: int, prepMinutes: int, cookMinutes: int, ingredients: list<string>, steps: list<string>}|null
     */
    #[Computed]
    public function recipe(): ?array
    {
        return Recipes::find((int) $this->param('id'));
    }

    public function render(): View
    {
        return view('native.recipe-detail');
    }
}
