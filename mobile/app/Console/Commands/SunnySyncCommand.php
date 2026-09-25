<?php

namespace App\Console\Commands;

use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;

#[Signature('sunny:sync')]
#[Description('Synchronize Sunny data opportunistically in the background')]
class SunnySyncCommand extends Command
{
    public function handle(SunnySyncCoordinator $coordinator, SunnyStore $store): int
    {
        if (! $store->isStale()) {
            return self::SUCCESS;
        }

        try {
            $coordinator->sync();
        } catch (AuthenticationException|UnauthorizedException) {
            $this->warn('Sunny authentication is unavailable.');
        } catch (RequestException|FatalRequestException|RuntimeException $exception) {
            report($exception);
        }

        return self::SUCCESS;
    }
}
