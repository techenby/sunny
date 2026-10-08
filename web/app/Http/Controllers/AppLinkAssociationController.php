<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AppLinkAssociationController extends Controller
{
    public function apple(): JsonResponse
    {
        $teamId = config('services.sunny_app.apple_team_id');

        abort_if(blank($teamId), 404);

        return response()->json([
            'applinks' => [
                'details' => [[
                    'appIDs' => [$teamId . '.' . config('services.sunny_app.id')],
                    'components' => collect(config('services.sunny_app.link_paths'))
                        ->map(fn (string $path): array => ['/' => $path])
                        ->all(),
                ]],
            ],
        ]);
    }

    public function android(): JsonResponse
    {
        $fingerprints = config('services.sunny_app.android_sha256_cert_fingerprints');

        abort_if($fingerprints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('services.sunny_app.id'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }
}
