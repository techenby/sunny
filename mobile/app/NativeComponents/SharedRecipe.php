<?php

namespace App\NativeComponents;

use App\Actions\OpenSharedRecipe;
use App\Actions\SaveSharedRecipe;
use App\Casts\RecipeLines;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Support\Offline;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\Lazy;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

#[Lazy]
class SharedRecipe extends Component
{
    public string $state = 'loading';

    public string $message = '';

    /** @var array<string, mixed>|null */
    public ?array $recipe = null;

    public ?string $saveProblem = null;

    public string $saveError = '';

    public ?string $saveUuid = null;

    public ?int $savedId = null;

    public bool $alreadySaved = false;

    public function mount(): void
    {
        if (app(SunnyStore::class)->lastSyncedAt() === null) {
            $this->replace('/');

            return;
        }

        $this->load();
    }

    public function load(): void
    {
        $this->state = 'loading';
        $this->message = '';

        if (Network::status()?->connected === false) {
            $this->loaded(['status' => 'offline']);

            return;
        }

        try {
            $token = app(SunnyTokenStore::class)->get();
        } catch (RuntimeException) {
            $this->loaded(['status' => 'error', 'message' => 'Unlock your phone, then try again.']);

            return;
        }

        if ($token === null) {
            $this->loaded(['status' => 'error', 'message' => 'Sign in to Sunny to open shared recipes.']);

            return;
        }

        $shareToken = (string) $this->param('token');

        AsyncTask::dispatch(static fn (): array => app(OpenSharedRecipe::class)->handle($shareToken, $token))
            ->finished(fn (array $result) => $this->loaded($result))
            ->failed(fn () => $this->loaded(app(OpenSharedRecipe::class)->handle($shareToken, $token)));
    }

    public function teamName(): ?string
    {
        $team = app(SunnyTeam::class)->current();

        return $team === null ? null : (app(SunnyTeam::class)->choices()[$team->id] ?? $team->name);
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        return collect([
            'Servings' => $this->recipe['servings'] ?? null,
            'Prep time' => $this->recipe['prep_time'] ?? null,
            'Cook time' => $this->recipe['cook_time'] ?? null,
            'Total time' => $this->recipe['total_time'] ?? null,
        ])->filter(fn (mixed $value): bool => is_string($value) && $value !== '')->all();
    }

    /**
     * @return list<array{type: string, text: string, number?: int|null}>
     */
    public function lines(string $field): array
    {
        $value = $this->recipe[$field] ?? null;
        $rows = is_string($value) ? RecipeLines::fromText($value) : (is_array($value) ? $value : []);
        $number = 0;

        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && is_string($row['text'] ?? null))
            ->map(function (array $row) use (&$number): array {
                $heading = ($row['type'] ?? null) === 'heading';

                return ['type' => $heading ? 'heading' : 'item', 'text' => $row['text'], 'number' => $heading ? null : ++$number];
            })
            ->values()
            ->all();
    }

    public function openSource(): void
    {
        if (Recipes::isSourceUrl($this->recipe['source'] ?? null)) {
            Browser::inApp($this->recipe['source']);
        }
    }

    public function save(): void
    {
        if ($this->recipe === null || $this->savedId !== null) {
            return;
        }

        $this->saveProblem = null;
        $this->saveError = '';
        $team = app(SunnyTeam::class)->current();

        if ($team === null) {
            $this->saveFailed(SunnySyncCoordinator::REFRESH_ADVICE);

            return;
        }

        if (Network::status()?->connected === false) {
            $this->saveProblem = 'offline';

            return;
        }

        $this->saveUuid ??= (string) Str::uuid();

        try {
            $saved = app(SaveSharedRecipe::class)->handle((string) $this->param('token'), $team, $this->saveUuid);
        } catch (FatalRequestException) {
            $this->saveProblem = 'offline';

            return;
        } catch (RequestException $exception) {
            $status = $exception->getResponse()->status();

            if ($status === 404) {
                $this->state = 'gone';
                $this->recipe = null;

                return;
            }

            $this->saveFailed(match ($status) {
                403 => 'You can’t add recipes to '.$this->teamName().'.',
                422 => $this->teamName().' isn’t one of your teams anymore.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'Sunny couldn’t save it right now.',
            });

            return;
        } catch (AuthenticationException|RuntimeException) {
            $this->saveFailed('Unable to read your saved login. Unlock your device and try again.');

            return;
        } catch (JsonException) {
            $saved = null;
        }

        if ($saved === null) {
            $this->saveFailed('Sunny didn’t confirm the save.');

            return;
        }

        $this->savedId = $saved['id'];
        $this->alreadySaved = ! $saved['created'];
    }

    public function openSaved(): void
    {
        if ($this->savedId !== null) {
            $this->navigate('/recipes/'.$this->savedId);
        }
    }

    public function goToRecipes(): void
    {
        $this->navigate('/recipes');
    }

    /**
     * @return array{title: string, message: string}
     */
    public function saveNote(): array
    {
        return $this->saveProblem === 'offline'
            ? ['title' => Offline::TITLE, 'message' => Offline::ADVICE]
            : ['title' => 'Couldn’t save', 'message' => $this->saveError.' Nothing’s been saved yet.'];
    }

    /**
     * @param  array{status: string, recipe?: array<string, mixed>, message?: string}  $result
     */
    protected function loaded(array $result): void
    {
        $this->state = $result['status'];
        $this->message = $result['message'] ?? '';
        $this->recipe = $result['status'] === 'found' ? ($result['recipe'] ?? null) : null;
    }

    protected function saveFailed(string $message): void
    {
        $this->saveProblem = 'failed';
        $this->saveError = $message;
    }

    protected function placeholder(): View
    {
        return $this->render();
    }

    public function render(): View
    {
        return view('native.shared-recipe');
    }
}
