<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Calendars\RefreshCalendarFeed;
use App\Models\CalendarFeed;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Description('Download every calendar feed and keep the last good copy of each')]
#[Signature('calendars:refresh')]
class RefreshCalendarFeedsCommand extends Command
{
    public function handle(RefreshCalendarFeed $refresh): int
    {
        $refreshed = 0;
        $failed = 0;

        CalendarFeed::query()->lazyById()->each(function (CalendarFeed $feed) use ($refresh, &$refreshed, &$failed): void {
            try {
                $ics = $refresh->handle($feed);
            } catch (Throwable $exception) {
                report($exception);

                $ics = null;
            }

            if ($ics === null) {
                $failed++;

                return;
            }

            $refreshed++;
        });

        $this->components->info(__(':count calendar feed(s) refreshed.', ['count' => $refreshed]));

        if ($failed > 0) {
            $this->components->warn(__(':count calendar feed(s) could not be refreshed.', ['count' => $failed]));
        }

        return self::SUCCESS;
    }
}
