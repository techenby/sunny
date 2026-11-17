<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MoveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('move', $this->item());
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
                Rule::notIn([$this->item()->team_id]),
                Rule::exists('team_members', 'team_id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    private function item(): Item
    {
        $item = $this->route('item');

        throw_unless($item instanceof Item, ModelNotFoundException::class);

        return $item;
    }
}
