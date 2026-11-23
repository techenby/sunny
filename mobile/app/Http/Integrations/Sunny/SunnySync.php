<?php

namespace App\Http\Integrations\Sunny;

use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\Requests\GetRoutineOccurrencesRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Models\Team;
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
        $rules['teams.*.is_personal'] = ['sometimes', 'boolean'];
        $rules['teams.*.week_start'] = ['nullable', 'integer', 'between:0,6'];
        $rules['recipes.*.photo_url'] = ['nullable', 'url'];
        $rules['items.*.photo_url'] = ['nullable', 'url'];
        $rules['recipes.*.thumb_url'] = ['nullable', 'url'];
        $rules['recipes.*.share_url'] = ['nullable', 'url'];
        $rules['items.*.thumb_url'] = ['nullable', 'url'];
        $rules['items.*.photos'] = ['sometimes', 'array', 'list'];
        $rules['items.*.photos.*.id'] = ['required', 'integer', 'min:1'];
        $rules['items.*.photos.*.url'] = ['required', 'url'];
        $rules['items.*.photos.*.thumb_url'] = ['nullable', 'url'];
        $rules['items.*.photos.*.position'] = ['required', 'integer', 'min:0'];

        $rules['items.*.type'] = ['required', Rule::enum(ItemType::class)];
        $rules['items.*.metadata'] = ['nullable', 'array'];
        $rules['checklists.*.type'] = ['required', Rule::enum(ChecklistType::class)];
        $rules['checklists.*.user_id'] = ['nullable', 'integer'];
        $rules['checklist_items.*.checklist_id'] = ['required', 'integer', 'min:1'];
        $rules['checklist_items.*.position'] = ['nullable', 'integer'];
        $rules['checklist_items.*.url'] = ['nullable', 'url'];
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

        foreach (['source', 'servings', 'prep_time', 'cook_time', 'total_time', 'description', 'notes', 'nutrition'] as $field) {
            $rules['recipes.*.'.$field] = ['nullable', 'string'];
        }

        foreach (['ingredients', 'instructions'] as $field) {
            $rules['recipes.*.'.$field] = ['nullable', 'array', 'list'];
            $rules['recipes.*.'.$field.'.*.type'] = ['required', 'string'];
            $rules['recipes.*.'.$field.'.*.text'] = ['required', 'string'];
        }

        $rules = [...$rules, ...$this->routineOccurrenceRules('routine_occurrences')];

        Validator::make($snapshot, $rules)->validate();

        $this->store->applySnapshot($snapshot);
        $this->thumbnails->refresh();
    }

    public function fetchRoutineDay(int $teamId, string $date, #[\SensitiveParameter] ?string $token = null): void
    {
        $team = Team::query()->where('server', SunnyStore::server())->findOrFail($teamId);
        throw_unless($team->slug, UnexpectedValueException::class, 'The team has not been downloaded yet.');
        $response = $this->auth->authenticatedConnector($token)->send(new GetRoutineOccurrencesRequest($team->slug, $date))->json();
        throw_unless(is_array($response), UnexpectedValueException::class, 'Invalid routines response.');

        Validator::make($response, $this->routineOccurrenceRules('data'))->validate();

        $this->store->saveRoutineDay($team->id, $date, collect($response['data']));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function routineOccurrenceRules(string $key): array
    {
        return [
            $key => ['present', 'array', 'list'],
            $key.'.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            $key.'.*.routine_id' => ['required', 'integer', 'min:1'],
            $key.'.*.due_on' => ['required', 'date_format:Y-m-d'],
            $key.'.*.routine' => ['required', 'array'],
            $key.'.*.routine.team_id' => ['required', 'integer', 'min:1'],
            $key.'.*.routine.name' => ['required', 'string'],
            $key.'.*.routine.time_of_day' => ['required', Rule::enum(TimeOfDay::class)],
            $key.'.*.routine.user' => ['nullable', 'array'],
            $key.'.*.routine.user.name' => ['required_with:'.$key.'.*.routine.user', 'string'],
            $key.'.*.steps' => ['present', 'array', 'list'],
            $key.'.*.steps.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            $key.'.*.steps.*.name' => ['required', 'string'],
            $key.'.*.steps.*.position' => ['nullable', 'integer'],
            $key.'.*.steps.*.completed_at' => ['nullable', 'date'],
        ];
    }
}
