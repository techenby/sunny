<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class UnusedFiles
{
    /**
     * @param  Collection<string, mixed>  $inUse  keyed by path
     */
    public static function delete(string $directory, Collection $inUse, bool $includingNew = false): void
    {
        $settledBefore = now()->subMinute()->getTimestamp();

        foreach (File::glob($directory.'/*') as $path) {
            if ($inUse->has($path)) {
                continue;
            }

            if (! $includingNew) {
                $modified = @filemtime($path);

                if ($modified === false || $modified >= $settledBefore) {
                    continue;
                }
            }

            File::delete($path);
        }
    }
}
