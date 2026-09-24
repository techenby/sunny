<?php

namespace App\Http\Integrations\Sunny;

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class SunnyWrites
{
    public function __construct(private readonly SunnyAuth $auth) {}

    /** Send once; the caller persists the confirmed response separately. */
    public function save(string $resource, int $teamId, array $payload, ?int $id = null, ?string $photoPath = null): array
    {
        $team = Team::query()->where('server', SunnyStore::server())->find($teamId);
        if (! $team || ! $team->slug) {
            throw ValidationException::withMessages(['team' => 'Sync with Sunny before saving to this team.']);
        }
        throw_unless(in_array($resource, ['recipes', 'items'], true), UnexpectedValueException::class);
        $model = $resource === 'recipes' ? Recipe::class : Item::class;
        if ($id !== null && ! $model::forCurrentServer()->where('team_id', $teamId)->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['record' => 'This record is no longer available. Sync with Sunny.']);
        }
        if ($resource === 'items' && ($payload['parent_id'] ?? null) !== null) {
            if (! Item::forCurrentServer()->where('team_id', $teamId)->whereKey($payload['parent_id'])->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'Choose a parent in the same team.']);
            }
        }
        if ($photoPath !== null) {
            if (! is_file($photoPath) || ! is_readable($photoPath)) {
                throw ValidationException::withMessages(['photo' => 'Choose the photo again; its file is no longer available.']);
            }
            Validator::make(['photo' => new UploadedFile($photoPath, basename($photoPath), test: true)], [
                'photo' => ['image', 'max:10240'],
            ])->validate();
        }

        $record = $this->auth->authenticatedConnector()->send(new SaveRecordRequest($team->slug, $resource, $payload, $id, $photoPath))->json('data');
        throw_unless(is_array($record) && is_int($record['id'] ?? null) && $record['id'] > 0
            && ($record['team_id'] ?? null) === $teamId && ($id === null || $record['id'] === $id),
            UnexpectedValueException::class, 'Sunny returned an invalid saved record.');

        return $record;
    }
}
