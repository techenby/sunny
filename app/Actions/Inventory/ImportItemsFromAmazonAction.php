<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\SimpleExcel\SimpleExcelReader;

final readonly class ImportItemsFromAmazonAction
{
    public function handle(TemporaryUploadedFile $file, Team $team, ?int $parentId = null): array
    {
        $parent = Item::find($parentId);
        abort_if($parent && $parent->team_id !== $team->id, 403);

        $stats = [
            'imported' => 0,
            'skipped' => 0,
        ];

        $toImport = SimpleExcelReader::create($file->getRealPath())->getRows()
            ->reject(function (array $row) use ($stats) {
                if ($row['Gift Message'] !== 'Not Available' || $row['Gift Recipient Contact'] !== 'Not Available' || $row['Gift Sender Name'] !== 'Not Available') {
                    $stats['skipped']++;

                    return true;
                }

                return false;
            })
            ->map(function(array $row) use ($parent) {
                return [
                    'type' => ItemType::Item,
                    'parent_id' => $parent?->id,
                    'name' => $row['Product Name'],
                    'metadata' => [
                        'Amount Paid' => $row['Total Amount'],
                        'ASIN' => $row['ASIN'],
                        'Purchased On' => Carbon::parse($row['Order Date']),
                        'Website' => $row['Website'],
                    ],
                ];
            });

        $team->items()->createMany($toImport);

        return $stats;
    }
}
