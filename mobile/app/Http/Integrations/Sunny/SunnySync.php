<?php

namespace App\Http\Integrations\Sunny;

use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use UnexpectedValueException;

class SunnySync
{
    public function __construct(private readonly SunnyAuth $auth, private readonly SunnyStore $store, private readonly SunnyThumbnails $thumbnails) {}

    /**
     * Pass the token when syncing off the UI thread, where secure storage is not available.
     */
    public function sync(#[\SensitiveParameter] ?string $token = null): void
    {
        $snapshot = $this->auth->authenticatedConnector($token)->send(new SyncRequest)->json();
        throw_unless(is_array($snapshot), UnexpectedValueException::class, 'Invalid sync response.');

        $rules = ['synced_at' => ['required', 'date']];

        foreach (['teams', 'recipes', 'items', 'checklists', 'checklist_items', 'routines', 'routine_steps'] as $type) {
            $rules[$type] = ['present', 'array', 'list'];
            $rules[$type.'.*.id'] = ['required', 'integer', 'min:1', 'distinct'];
            $rules[$type.'.*.name'] = ['required', 'string'];
            $rules[$type.'.*.deleted_at'] = ['nullable', 'date'];
        }

        foreach (['recipes', 'items', 'checklists', 'checklist_items', 'routines', 'routine_steps'] as $type) {
            $rules[$type.'.*.created_at'] = ['required', 'date'];
            $rules[$type.'.*.updated_at'] = ['required', 'date'];
        }

        foreach (['recipes', 'items', 'checklists', 'routines'] as $type) {
            $rules[$type.'.*.team_id'] = ['required', 'integer', 'min:1'];
        }

        foreach (['recipes', 'items'] as $type) {
            $rules[$type.'.*.parent_id'] = ['nullable', 'integer'];
        }

        $rules['teams.*.slug'] = ['nullable', 'string'];
        $rules['teams.*.timezone'] = ['nullable', 'timezone'];
        $rules['recipes.*.photo_url'] = ['nullable', 'url'];
        $rules['items.*.photo_url'] = ['nullable', 'url'];
        $rules['recipes.*.thumb_url'] = ['nullable', 'url'];
        $rules['items.*.thumb_url'] = ['nullable', 'url'];

        $rules['items.*.type'] = ['required', Rule::enum(ItemType::class)];
        $rules['items.*.metadata'] = ['nullable', 'array'];
        $rules['checklists.*.type'] = ['required', Rule::enum(ChecklistType::class)];
        $rules['checklists.*.user_id'] = ['nullable', 'integer'];
        $rules['checklist_items.*.checklist_id'] = ['required', 'integer', 'min:1'];
        $rules['checklist_items.*.position'] = ['nullable', 'integer'];
        $rules['checklist_items.*.completed_at'] = ['nullable', 'date'];
        $rules['checklist_items.*.completed_by'] = ['nullable', 'integer'];
        $rules['routines.*.user_id'] = ['nullable', 'integer'];
        $rules['routines.*.time_of_day'] = ['required', Rule::enum(TimeOfDay::class)];
        $rules['routines.*.frequency'] = ['required', Rule::enum(RoutineFrequency::class)];
        $rules['routines.*.weekdays'] = ['nullable', 'array'];
        $rules['routines.*.weekdays.*'] = ['integer', 'between:0,6'];
        $rules['routines.*.day_of_month'] = ['nullable', 'integer', 'between:1,31'];
        $rules['routines.*.starts_on'] = ['nullable', 'date'];
        $rules['routines.*.is_active'] = ['required', 'boolean'];
        $rules['routine_steps.*.routine_id'] = ['required', 'integer', 'min:1'];
        $rules['routine_steps.*.position'] = ['nullable', 'integer'];
        $rules['recipes.*.tags'] = ['nullable', 'array'];
        $rules['recipes.*.tags.*'] = ['string'];

        foreach (['source', 'servings', 'prep_time', 'cook_time', 'total_time', 'description', 'ingredients', 'instructions', 'notes', 'nutrition'] as $field) {
            $rules['recipes.*.'.$field] = ['nullable', 'string'];
        }

        $rules['routine_occurrences'] = ['present', 'array', 'list'];
        $rules['routine_occurrences.*.id'] = ['required', 'integer', 'min:1', 'distinct'];
        $rules['routine_occurrences.*.routine_id'] = ['required', 'integer', 'min:1'];
        $rules['routine_occurrences.*.due_on'] = ['required', 'date_format:Y-m-d'];
        $rules['routine_occurrences.*.routine'] = ['required', 'array'];
        $rules['routine_occurrences.*.routine.team_id'] = ['required', 'integer', 'min:1'];
        $rules['routine_occurrences.*.routine.name'] = ['required', 'string'];
        $rules['routine_occurrences.*.routine.time_of_day'] = ['required', Rule::enum(TimeOfDay::class)];
        $rules['routine_occurrences.*.routine.user'] = ['nullable', 'array'];
        $rules['routine_occurrences.*.routine.user.name'] = ['required_with:routine_occurrences.*.routine.user', 'string'];
        $rules['routine_occurrences.*.steps'] = ['present', 'array', 'list'];
        $rules['routine_occurrences.*.steps.*.id'] = ['required', 'integer', 'min:1', 'distinct'];
        $rules['routine_occurrences.*.steps.*.name'] = ['required', 'string'];
        $rules['routine_occurrences.*.steps.*.position'] = ['nullable', 'integer'];
        $rules['routine_occurrences.*.steps.*.completed_at'] = ['nullable', 'date'];

        Validator::make($snapshot, $rules)->validate();

        $this->store->applySnapshot($snapshot);
        $this->thumbnails->refresh();
    }
}
