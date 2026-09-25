<?php

namespace Sunny\ItemScanner;

use Illuminate\Support\Str;

class ItemScanner
{
    /**
     * Whether the on-device model can identify items in photos. When it
     * can't, `reason` is one of: bridgeUnavailable, unsupportedOS,
     * deviceNotEligible, appleIntelligenceNotEnabled, modelNotReady, or
     * visionUnsupported.
     *
     * @return array{available: bool, reason: string|null}
     */
    public function availability(): array
    {
        if (! function_exists('nativephp_can') || ! nativephp_can('ItemScanner.Availability')) {
            return ['available' => false, 'reason' => 'bridgeUnavailable'];
        }

        $result = json_decode((string) nativephp_call('ItemScanner.Availability', '{}'), true);

        return [
            'available' => ($result['available'] ?? false) === true,
            'reason' => ($result['available'] ?? false) === true ? null : ($result['reason'] ?? 'bridgeUnavailable'),
        ];
    }

    /**
     * Start identifying the items in a photo. The result arrives as an
     * ItemsIdentified or IdentificationFailed event carrying the returned id.
     *
     * @param  list<string>  $knownNames  Items already in inventory, for duplicate matching.
     * @param  string|null  $place  Where the photo was taken, e.g. "Garage › Tool chest".
     */
    public function identify(string $path, array $knownNames = [], ?string $place = null, ?string $id = null): string
    {
        $id ??= (string) Str::uuid();

        if (function_exists('nativephp_call')) {
            nativephp_call('ItemScanner.Identify', json_encode([
                'path' => $path,
                'id' => $id,
                'knownNames' => array_values($knownNames),
                'place' => $place,
            ]));
        }

        return $id;
    }
}
