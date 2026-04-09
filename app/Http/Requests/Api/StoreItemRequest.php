<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Item::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:items,id'],
            'metadata' => ['nullable', 'array'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ];
    }
}
