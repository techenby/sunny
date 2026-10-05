<?php

namespace App\Models;

use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class ScanDraft extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['candidates' => 'array'];
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
