<?php

namespace App\NativeComponents;

use App\Icons\Android;
use App\Icons\Ios;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class Inventory extends NativeComponent
{
    /** @var array<string, array{ios: Ios, android: Android}> */
    private const LOCATION_ICONS = [
        'Pantry' => ['ios' => Ios::Cabinet, 'android' => Android::Kitchen],
        'Garage' => ['ios' => Ios::Wrench, 'android' => Android::Garage],
        'Basement' => ['ios' => Ios::Shippingbox, 'android' => Android::Inventory2],
    ];

    /**
     * Placeholder inventory until the app syncs with sunnyhome.app.
     *
     * @return list<array{id: int, name: string, location: string, spot: string, quantity: int, notes: string}>
     */
    public static function all(): array
    {
        return [
            ['id' => 1, 'name' => 'Canned tomatoes', 'location' => 'Pantry', 'spot' => 'Middle shelf', 'quantity' => 6, 'notes' => 'Crushed and diced. Used in the chili and lasagna.'],
            ['id' => 2, 'name' => 'Olive oil', 'location' => 'Pantry', 'spot' => 'Top shelf', 'quantity' => 2, 'notes' => 'One bottle is open.'],
            ['id' => 3, 'name' => 'Basmati rice', 'location' => 'Pantry', 'spot' => 'Bottom bin', 'quantity' => 1, 'notes' => '10 lb bag, about half left.'],
            ['id' => 4, 'name' => 'Cordless drill', 'location' => 'Garage', 'spot' => 'Workbench', 'quantity' => 1, 'notes' => 'Spare battery is on the charger.'],
            ['id' => 5, 'name' => 'Extension cords', 'location' => 'Garage', 'spot' => 'Wall hooks', 'quantity' => 3, 'notes' => 'Two 25 ft and one 50 ft outdoor cord.'],
            ['id' => 6, 'name' => 'Camping tent', 'location' => 'Garage', 'spot' => 'Overhead rack', 'quantity' => 1, 'notes' => 'Sleeps four. Stakes are in the side pocket.'],
            ['id' => 7, 'name' => 'Holiday decorations', 'location' => 'Basement', 'spot' => 'Storage room', 'quantity' => 4, 'notes' => 'Bins are labeled by holiday.'],
            ['id' => 8, 'name' => 'Paper towels', 'location' => 'Basement', 'spot' => 'Shelf B', 'quantity' => 12, 'notes' => 'Bulk pack.'],
            ['id' => 9, 'name' => 'Spare light bulbs', 'location' => 'Basement', 'spot' => 'Utility closet', 'quantity' => 8, 'notes' => 'Soft white LED, 60 W equivalent.'],
        ];
    }

    /**
     * @return array{id: int, name: string, location: string, spot: string, quantity: int, notes: string}|null
     */
    public static function find(int $id): ?array
    {
        return collect(static::all())->firstWhere('id', $id);
    }

    /**
     * @return list<array{location: string, ios: Ios, android: Android, items: list<array{id: int, name: string, location: string, spot: string, quantity: int, notes: string}>}>
     */
    #[Computed(persist: true)]
    public function locations(): array
    {
        return collect(static::all())
            ->groupBy('location')
            ->map(fn ($items, string $location): array => [
                'location' => $location,
                ...self::LOCATION_ICONS[$location],
                'items' => $items->all(),
            ])
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('native.inventory');
    }
}
