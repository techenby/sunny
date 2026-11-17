<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

class ClientUuid
{
    /**
     * @template TModel of Model
     *
     * @param  HasMany<TModel, *>  $scope
     * @param  Closure(): TModel  $create
     * @return TModel
     */
    public static function firstOrCreate(HasMany $scope, ?string $clientUuid, Closure $create): Model
    {
        if (blank($clientUuid)) {
            return $create();
        }

        $query = $scope->getQuery();

        $existing = (clone $query)->firstWhere('client_uuid', $clientUuid);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return $query->withSavepointIfNeeded($create);
        } catch (UniqueConstraintViolationException $e) {
            return $query->useWritePdo()->firstWhere('client_uuid', $clientUuid) ?? throw $e;
        }
    }
}
