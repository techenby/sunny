<?php

namespace App\NativeComponents;

use App\Actions\ImportRecipe;
use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRecipeForm;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Layouts\HidesTabBar;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Facades\Network;
use RuntimeException;

class CreateRecipe extends Component implements HidesTabBar
{
    use ChecksSunnySync;
    use ManagesRecipeForm;

    public bool $importing = false;

    public string $importProblem = '';

    public string $importMessage = '';

    public bool $importDismissed = false;

    public ?string $importUrl = null;

    public ?string $importUuid = null;

    public ?int $importedId = null;

    public function mount(): void
    {
        $this->initializeTeam();
    }

    /**
     * @return array{url: string, host: string, path: string, initial: string, state: string, message: string}|null
     */
    #[Computed]
    public function importCard(): ?array
    {
        $link = $this->sourceLink();

        if ($link === null || $this->importDismissed) {
            return null;
        }

        $state = $this->importing ? 'importing' : ($this->importProblem ?: 'ready');

        $message = $state === 'no-recipe'
            ? ($link['path'] === '' ? 'That looks like a home page.' : $this->importMessage).' Try the link to the recipe itself, or fill it in yourself.'
            : $this->importMessage;

        return [...$link, 'state' => $state, 'message' => $message];
    }

    public function updatedSource(string $value): void
    {
        if (trim($value) !== $this->importUrl) {
            $this->importProblem = '';
            $this->importMessage = '';
            $this->importDismissed = false;
        }
    }

    public function importRecipe(): void
    {
        $link = $this->sourceLink();

        if ($this->importing || $this->importedId !== null || $link === null) {
            return;
        }

        if ($link['url'] !== $this->importUrl) {
            $this->importUrl = $link['url'];
            $this->importUuid = (string) Str::uuid();
        }

        $this->importProblem = '';
        $this->importMessage = '';
        $team = app(SunnyTeam::class)->current();

        if ($team === null || $team->id !== $this->teamId || blank($team->slug)) {
            $this->importFinished(['status' => 'error', 'message' => SunnySyncCoordinator::NO_TEAM_ADVICE]);

            return;
        }

        if (Network::status()?->connected === false) {
            $this->importFinished(['status' => 'offline']);

            return;
        }

        try {
            $token = app(SunnyTokenStore::class)->get();
        } catch (RuntimeException) {
            $this->importFinished(['status' => 'error', 'message' => 'Unlock your phone, then try again.']);

            return;
        }

        if ($token === null) {
            $this->importFinished(['status' => 'error', 'message' => 'Sign in to import recipes.']);

            return;
        }

        $this->importing = true;
        [$teamId, $teamSlug, $url, $uuid] = [$team->id, $team->slug, $link['url'], $this->importUuid];

        AsyncTask::dispatch(static fn (): array => app(ImportRecipe::class)->handle($teamId, $teamSlug, $url, $uuid, $token))
            ->finished(fn (array $result) => $this->importFinished($result))
            ->failed(fn () => $this->importFinished(['status' => 'error', 'message' => 'The import couldn’t start. Try again.']));
    }

    public function changeImportLink(): void
    {
        $this->source = '';
        $this->updatedSource('');
    }

    public function fillInMyself(): void
    {
        $this->importDismissed = true;
    }

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('recipes', $this->recipePayload());
    }

    public function render(): View
    {
        return view('native.create-recipe');
    }

    /**
     * @param  array{status: string, id?: int, message?: string}  $result
     */
    protected function importFinished(array $result): void
    {
        $this->importing = false;

        if ($result['status'] === 'verify') {
            $this->openEmailVerification();

            return;
        }

        if ($result['status'] === 'imported' && isset($result['id'])) {
            $this->importedId = $result['id'];
            $this->replace('/recipes/'.$result['id'].'/edit', ['imported' => true]);

            return;
        }

        $this->importProblem = $result['status'];
        $this->importMessage = $result['message'] ?? '';
    }
}
