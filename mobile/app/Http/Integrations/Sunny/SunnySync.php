<?php

namespace App\Http\Integrations\Sunny;

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

class SunnySync
{
    public function __construct(private readonly SunnyAuth $auth, private readonly SunnyStore $store) {}

    public function sync(): void
    {
        $snapshot = $this->auth->authenticatedConnector()->send(new SyncRequest)->json();
        throw_unless(is_array($snapshot), UnexpectedValueException::class, 'Invalid sync response.');

        $rules = ['synced_at' => ['required', 'date']];

        foreach (['teams', 'recipes', 'items'] as $type) {
            $rules[$type] = ['present', 'array', 'list'];
            $rules[$type.'.*.id'] = ['required', 'integer', 'min:1', 'distinct'];
            $rules[$type.'.*.name'] = ['required', 'string'];
            $rules[$type.'.*.deleted_at'] = ['nullable', 'date'];
        }

        foreach (['recipes', 'items'] as $type) {
            $rules[$type.'.*.team_id'] = ['required', 'integer', 'min:1'];
            $rules[$type.'.*.parent_id'] = ['nullable', 'integer'];
            $rules[$type.'.*.created_at'] = ['required', 'date'];
            $rules[$type.'.*.updated_at'] = ['required', 'date'];
        }

        $rules['teams.*.slug'] = ['nullable', 'string'];
        $rules['recipes.*.photo_url'] = ['nullable', 'url'];
        $rules['items.*.photo_url'] = ['nullable', 'url'];

        $rules['items.*.type'] = ['required', Rule::enum(ItemType::class)];
        $rules['items.*.metadata'] = ['nullable', 'array'];
        $rules['recipes.*.tags'] = ['nullable', 'array'];
        $rules['recipes.*.tags.*'] = ['string'];

        foreach (['source', 'servings', 'prep_time', 'cook_time', 'total_time', 'description', 'ingredients', 'instructions', 'notes', 'nutrition'] as $field) {
            $rules['recipes.*.'.$field] = ['nullable', 'string'];
        }

        Validator::make($snapshot, $rules)->validate();
        $snapshot['recipes'] = array_map(fn (array $recipe): array => $recipe + array_fill_keys([
            'photo_url', 'parent_id', 'source', 'servings', 'prep_time', 'cook_time', 'total_time', 'description', 'ingredients', 'instructions', 'notes', 'nutrition', 'tags',
        ], null), $snapshot['recipes']);
        $snapshot['items'] = array_map(fn (array $item): array => $item + ['parent_id' => null, 'metadata' => null, 'photo_url' => null], $snapshot['items']);

        $this->store->applySnapshot($snapshot);
    }
}
