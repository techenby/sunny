<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Validation\ValidationException;

trait LimitedPerOwner
{
    public static function bootLimitedPerOwner(): void
    {
        static::creating(function (self $model): void {
            $column = $model->ownerColumn();
            $count = $model->newQueryWithoutScopes()->where($column, $model->getAttribute($column))->count();

            throw_if($count >= static::MAX_PER_OWNER, ValidationException::withMessages([
                'name' => [$model->ownerLimitMessage()],
            ]));
        });
    }

    abstract protected function ownerColumn(): string;

    abstract protected function ownerLimitMessage(): string;
}
