<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Onboarding\CompleteOnboarding;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __invoke(Request $request, CompleteOnboarding $completeOnboarding): UserResource
    {
        $completeOnboarding->handle($request->user());

        return UserResource::make($request->user()->fresh());
    }
}
