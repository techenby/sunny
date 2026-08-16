<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Routines\GenerateRoutineOccurrences;
use Illuminate\Console\Command;

class GenerateRoutineOccurrencesCommand extends Command
{
    protected $signature = 'routines:generate {--days=7 : How many days ahead to generate}';

    protected $description = 'Pre-generate routine occurrences so upcoming days are ready before anyone opens the board';

    public function handle(GenerateRoutineOccurrences $generate): int
    {
        $days = max(1, (int) $this->option('days'));

        $created = $generate->warm($days);

        $this->components->info(__(':count occurrence(s) generated across :days day(s).', [
            'count' => $created,
            'days' => $days,
        ]));

        return self::SUCCESS;
    }
}
