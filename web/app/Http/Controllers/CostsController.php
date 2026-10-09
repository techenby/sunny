<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class CostsController extends Controller
{
    public function __invoke(): View
    {
        $path = config('costs.cloud.path');
        $cloud = File::exists($path) ? File::json($path) : null;

        $services = array_filter(config('costs.services'), fn (?int $cents): bool => $cents !== null);

        return view('costs', [
            'cloud' => $cloud,
            'services' => $services,
            'totalCents' => array_sum($cloud['items'] ?? []) + array_sum($services),
            'syncedAt' => isset($cloud['synced_at']) ? Date::parse($cloud['synced_at']) : null,
        ]);
    }
}
