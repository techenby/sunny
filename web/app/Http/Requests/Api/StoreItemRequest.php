<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Actions\Photos\CreateThumbnail;
use App\Enums\ItemType;
use App\Models\Item;
use App\Rules\ItemMetadata;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
            'parent_id' => ['nullable', 'integer', Rule::exists('items', 'id')->where('team_id', $this->route('team')->id)],
            'metadata' => ['nullable', 'bail', 'array', new ItemMetadata],
            'photo' => ['nullable', 'image', 'max:10240', CreateThumbnail::DIMENSIONS_RULE],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parent_id.exists' => __('That place isn’t in this team.'),
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
