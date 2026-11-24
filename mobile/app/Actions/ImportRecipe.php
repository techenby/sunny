<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\EmailNotVerifiedException;
use App\Http\Integrations\Sunny\Requests\ImportRecipeRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Auth\AuthenticationException;
use JsonException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class ImportRecipe
{
    public const NO_RECIPE = 'No recipe was found at that address.';

    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /**
     * @return array{status: 'imported', id: int}|array{status: 'no-recipe'|'unreachable'|'error', message: string}|array{status: 'offline'|'verify'}
     */
    public function handle(int $teamId, string $teamSlug, string $url, string $clientUuid, #[\SensitiveParameter] ?string $token = null): array
    {
        try {
            $record = $this->auth->authenticatedConnector($token)
                ->send(new ImportRecipeRequest($teamSlug, $url, $clientUuid))
                ->json('data');
        } catch (FatalRequestException) {
            return ['status' => 'offline'];
        } catch (EmailNotVerifiedException) {
            return ['status' => 'verify'];
        } catch (RequestException $exception) {
            return match ($exception->getResponse()->status()) {
                422 => ['status' => 'no-recipe', 'message' => self::NO_RECIPE],
                424 => ['status' => 'unreachable', 'message' => 'That site didn’t allow the page to be read. Try another link, or fill it in yourself.'],
                401 => ['status' => 'error', 'message' => 'The server didn’t accept this phone’s sign-in. Sign out and back in, then try again.'],
                403 => ['status' => 'error', 'message' => 'You can’t add recipes to this team.'],
                429 => ['status' => 'error', 'message' => 'That’s a lot of imports in a row. Wait a minute and try again.'],
                default => ['status' => 'error', 'message' => 'The server is unavailable right now. Try again in a moment.'],
            };
        } catch (AuthenticationException) {
            return ['status' => 'error', 'message' => 'Sign in to import recipes.'];
        } catch (JsonException|RuntimeException) {
            return ['status' => 'error', 'message' => 'The server sent back something unexpected. Try again in a moment.'];
        }

        if (! is_array($record) || ! is_int($record['id'] ?? null) || ($record['team_id'] ?? null) !== $teamId) {
            return ['status' => 'error', 'message' => 'The server sent back something unexpected. Try again in a moment.'];
        }

        $this->store->saveRecord('recipes', $record);

        return ['status' => 'imported', 'id' => $record['id']];
    }
}
