<?php

namespace App\Models;

use App\Casts\StoredFilePath;
use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class ScanDraft extends Model
{
    protected $guarded = [];

    /** @return Attribute<list<array<string, mixed>>, list<array<string, mixed>>> */
    protected function candidates(): Attribute
    {
        return Attribute::make(
            get: fn (string $candidates): array => array_map(
                fn (array $candidate): array => isset($candidate['photoPath']) ? [...$candidate, 'photoPath' => StoredFilePath::current($candidate['photoPath'])] : $candidate,
                json_decode($candidates, true, flags: JSON_THROW_ON_ERROR),
            ),
            set: fn (array $candidates): string => json_encode($candidates, JSON_THROW_ON_ERROR),
        );
    }

    public function scopeForCurrentServer(Builder $query): void
    {
        $query->where('server', SunnyStore::server());
    }

    public function discard(): void
    {
        foreach ($this->candidates as $candidate) {
            self::forgetPhoto($candidate['photoPath'] ?? null);
        }

        $this->delete();
    }

    public static function photoDirectory(): string
    {
        return storage_path('app/scan-drafts');
    }

    public static function forgetPhoto(?string $path): void
    {
        if ($path !== null && str_starts_with($path, self::photoDirectory().'/')) {
            File::delete($path);
        }
    }
}
