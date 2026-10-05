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
     * Start identifying the item in a photo. The result arrives as an
     * ItemIdentified or IdentificationFailed event carrying the returned id.
     *
     * @param  string|null  $place  Where the photo was taken, e.g. "Garage › Tool chest".
     * @param  string|null  $batch  What the batch of photos is of, e.g. "Christmas ornaments".
     * @param  list<string>  $batchNames  Names already given to other items in the batch.
     */
    public function identify(string $path, ?string $place = null, ?string $batch = null, array $batchNames = [], ?string $id = null): string
    {
        $id ??= (string) Str::uuid();

        if (function_exists('nativephp_call')) {
            nativephp_call('ItemScanner.Identify', json_encode([
                'path' => $path,
                'id' => $id,
                'place' => $place,
                'batch' => $batch,
                'batchNames' => array_values($batchNames),
            ]));
        }

        return $id;
    }

    /**
     * Open a camera that stays up between shots. Each photo arrives as a
     * PhotoCaptured event, or CaptureFailed when the camera can't open.
     */
    public function capture(?string $id = null, bool $single = false): string
    {
        $id ??= (string) Str::uuid();

        if (function_exists('nativephp_call')) {
            nativephp_call('ItemScanner.Capture', json_encode([
                'id' => $id,
                'single' => $single,
            ]));
        }

        return $id;
    }
}
