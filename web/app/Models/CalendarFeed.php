<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarColor;
use Carbon\CarbonImmutable;
use Database\Factories\CalendarFeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @property CarbonImmutable|null $last_fetched_at
 * @property CarbonImmutable|null $last_failed_at
 * @property CarbonImmutable|null $ics_fetched_at
 */
#[Fillable(['team_id', 'name', 'url', 'color', 'client_uuid'])]
class CalendarFeed extends Model
{
    /** @use HasFactory<CalendarFeedFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleted(function (CalendarFeed $feed): void {
            Storage::delete($feed->icsPath());
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fetched(?Throwable $exception = null)
    {
        if ($exception) {
            $this->update([
                'last_failed_at' => now(),
                'last_error' => match (true) {
                    $exception instanceof RequestException => __('The calendar server responded with HTTP :status.', ['status' => $exception->response->status()]),
                    $exception instanceof ConnectionException => __('Could not connect to the calendar server.'),
                    default => __('The calendar feed could not be read.'),
                },
            ]);

            return;
        }

        $this->update([
            'last_fetched_at' => now(),
            'last_failed_at' => null,
            'last_error' => null,
        ]);
    }

    public function isFailing(): bool
    {
        return $this->last_failed_at !== null;
    }

    public function icsPath(): string
    {
        return "calendar-feeds/{$this->id}.ics";
    }

    public function storedIcs(): ?string
    {
        return $this->ics_fetched_at ? Storage::get($this->icsPath()) : null;
    }

    public function storeIcs(string $ics): void
    {
        if (Storage::put($this->icsPath(), $ics)) {
            $this->update(['ics_fetched_at' => now()]);
        }
    }

    public function forgetIcs(): void
    {
        Storage::delete($this->icsPath());

        $this->update(['ics_fetched_at' => null]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'color' => CalendarColor::class,
            'last_fetched_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'ics_fetched_at' => 'datetime',
        ];
    }
}
