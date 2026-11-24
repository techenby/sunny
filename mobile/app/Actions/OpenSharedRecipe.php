<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\EmailNotVerifiedException;
use App\Http\Integrations\Sunny\Requests\GetSharedRecipeRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use Illuminate\Auth\AuthenticationException;
use JsonException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class OpenSharedRecipe
{
    public function __construct(private readonly SunnyAuth $auth) {}

    /**
     * @return array{status: 'found', recipe: array<string, mixed>}|array{status: 'gone'|'offline'|'verify'}|array{status: 'error', message: string}
     */
    public function handle(string $shareToken, #[\SensitiveParameter] ?string $token = null): array
    {
        try {
            $recipe = $this->auth->authenticatedConnector($token)
                ->send(new GetSharedRecipeRequest($shareToken))
                ->json('data');
        } catch (FatalRequestException) {
            return ['status' => 'offline'];
        } catch (EmailNotVerifiedException) {
            return ['status' => 'verify'];
        } catch (RequestException $exception) {
            return match ($exception->getResponse()->status()) {
                404 => ['status' => 'gone'],
                401 => ['status' => 'error', 'message' => 'The server didn’t accept this phone’s sign-in. Sign out and back in, then try again.'],
                429 => ['status' => 'error', 'message' => 'Too many attempts. Please wait before trying again.'],
                default => ['status' => 'error', 'message' => 'The server is unavailable right now. Try again in a moment.'],
            };
        } catch (AuthenticationException) {
            return ['status' => 'error', 'message' => 'Sign in to open shared recipes.'];
        } catch (JsonException|RuntimeException) {
            return ['status' => 'error', 'message' => 'The server sent back something unexpected. Try again in a moment.'];
        }

        if (! is_array($recipe) || ! is_int($recipe['id'] ?? null) || ! is_string($recipe['name'] ?? null)) {
            return ['status' => 'error', 'message' => 'The server sent back something unexpected. Try again in a moment.'];
        }

        return ['status' => 'found', 'recipe' => $recipe];
    }
}
