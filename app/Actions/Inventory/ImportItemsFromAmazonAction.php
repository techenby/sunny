<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Spatie\SimpleExcel\SimpleExcelReader;

final readonly class ImportItemsFromAmazonAction
{
    /** @var list<string> */
    public const array CONSUMABLE_KEYWORDS = [
        'diet coke', 'coca-cola', 'pepsi', 'soda', 'sparkling water', 'energy drink',
        'shampoo', 'conditioner', 'body wash', 'soap', 'lotion', 'deodorant', 'toothpaste', 'toothbrush', 'mouthwash',
        'toilet paper', 'paper towel', 'tissues', 'napkins', 'trash bags', 'laundry detergent', 'dish soap', 'cleaning',
        'vitamins', 'supplements', 'protein powder', 'protein bar',
        'coffee', 'tea bags', 'k-cup', 'creamer',
        'snack', 'chips', 'crackers', 'cookies', 'candy', 'chocolate', 'gum',
        'cat food', 'dog food', 'pet treats',
        'batteries', 'light bulb',
        'band-aid', 'bandage', 'first aid',
        'razors', 'floss', 'cotton',
    ];

    /**
     * @param  array{filterGifts?: bool, filterConsumables?: bool, startDate?: string|null, endDate?: string|null}  $filters
     * @return array{imported: int, skipped: int}
     */
    public function handle(UploadedFile $file, Team $team, ?int $parentId = null, array $filters = []): array
    {
        $parent = Item::find($parentId);
        abort_if($parent && $parent->team_id !== $team->id, 403);

        $filterGifts = $filters['filterGifts'] ?? true;
        $filterConsumables = $filters['filterConsumables'] ?? false;
        $startDate = isset($filters['startDate']) ? Date::parse($filters['startDate']) : null;
        $endDate = isset($filters['endDate']) ? Date::parse($filters['endDate'])->endOfDay() : null;

        $stats = [
            'imported' => 0,
            'skipped' => 0,
        ];

        $toImport = SimpleExcelReader::create($file->getRealPath())->getRows()
            ->collect()
            ->reject(function (array $row) use (&$stats, $filterGifts, $filterConsumables, $startDate, $endDate) {
                if ($filterGifts && $this->isGift($row)) {
                    $stats['skipped']++;

                    return true;
                }

                if ($filterConsumables && $this->isConsumable($row['Product Name'])) {
                    $stats['skipped']++;

                    return true;
                }

                if ($startDate || $endDate) {
                    $orderDate = Date::parse($row['Order Date']);

                    if ($startDate && $orderDate->isBefore($startDate)) {
                        $stats['skipped']++;

                        return true;
                    }

                    if ($endDate && $orderDate->isAfter($endDate)) {
                        $stats['skipped']++;

                        return true;
                    }
                }

                return false;
            })
            ->map(function (array $row) use ($parent) {
                return [
                    'type' => ItemType::Item,
                    'parent_id' => $parent?->id,
                    'name' => html_entity_decode($row['Product Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'metadata' => [
                        'Amount Paid' => $row['Total Amount'],
                        'ASIN' => $row['ASIN'],
                        'Purchased On' => Date::parse($row['Order Date']),
                        'Website' => $row['Website'],
                    ],
                ];
            });

        $stats['imported'] = count($toImport);

        $team->items()->createMany($toImport);

        return $stats;
    }

    private function isGift(array $row): bool
    {
        return $row['Gift Message'] !== 'Not Available'
            || $row['Gift Recipient Contact'] !== 'Not Available'
            || $row['Gift Sender Name'] !== 'Not Available';
    }

    private function isConsumable(string $productName): bool
    {
        $name = mb_strtolower($productName);

        foreach (self::CONSUMABLE_KEYWORDS as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
