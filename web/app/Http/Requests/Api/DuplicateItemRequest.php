<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DuplicateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('item'))
            && Gate::allows('create', Item::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'count' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }
}
