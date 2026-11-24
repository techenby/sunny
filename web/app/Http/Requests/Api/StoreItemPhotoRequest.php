<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreItemPhotoRequest extends FormRequest
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
            'photo' => ['required', 'image', 'max:10240', CreateThumbnail::DIMENSIONS_RULE],
            'position' => ['nullable', 'integer', 'min:0'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function existingPhoto(): ?ItemPhoto
    {
        return Str::isUuid($this->input('client_uuid'))
            ? $this->item()->photos()->firstWhere('client_uuid', $this->input('client_uuid'))
            : null;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->existingPhoto() === null && $this->item()->photos()->count() >= ItemPhoto::MAX_PER_ITEM) {
                    $validator->errors()->add('photo', __('An item can have up to :count photos.', ['count' => ItemPhoto::MAX_PER_ITEM]));
                }
            },
        ];
    }

    private function item(): Item
    {
        $item = $this->route('item');

        throw_unless($item instanceof Item, ModelNotFoundException::class);

        return $item;
    }
}
