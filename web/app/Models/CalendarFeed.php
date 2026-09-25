<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarColor;
use Database\Factories\CalendarFeedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

#[Fillable(['team_id', 'name', 'url', 'color'])]
class CalendarFeed extends Model
{
    /** @use HasFactory<CalendarFeedFactory> */
    use HasFactory;

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'color' => CalendarColor::class,
            'last_fetched_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }
}
