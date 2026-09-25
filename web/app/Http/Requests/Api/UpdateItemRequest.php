<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\ItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('item'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remove_photo' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('team_id', $this->route('team')->id)],
            'metadata' => ['nullable', 'array'],
            'photo' => ['nullable', 'image', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('metadata'))) {
            $metadata = json_decode($this->input('metadata'), true);

            if (json_last_error() === JSON_ERROR_NONE && (is_array($metadata) || $metadata === null)) {
                $this->merge(['metadata' => $metadata]);
            }
        }
    }
}
