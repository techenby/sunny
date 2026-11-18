<?php

namespace App\Concerns;

use App\Actions\MoveItemToTeam;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\Team;
use App\NativeComponents\Inventory;
use App\Support\Toast;
use App\Support\ToastAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use JsonException;
use Native\Mobile\Attributes\On;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

trait MovesItemToTeam
{
    public bool $showTeamMove = false;

    public ?int $teamMoveTeamId = null;

    public ?string $teamMoveProblem = null;

    public string $teamMoveError = '';

    /**
     * @return array<int, string>
     */
    public function teamMoveTeams(): array
    {
        $teamId = $this->item['team_id'] ?? null;

        return collect(app(SunnyTeam::class)->choices())->except($teamId)->all();
    }

    public function openTeamMove(): void
    {
        $teams = $this->teamMoveTeams();

        if ($teams === []) {
            return;
        }

        if (! isset($teams[$this->teamMoveTeamId])) {
            $this->teamMoveTeamId = array_key_first($teams);
        }

        $this->teamMoveProblem = null;
        $this->teamMoveError = '';
        $this->showTeamMove = true;
    }

    public function closeTeamMove(): void
    {
        $this->showTeamMove = false;
        $this->teamMoveProblem = null;
        $this->teamMoveError = '';
    }

    public function pickTeamMoveTeam(int $id): void
    {
        if (isset($this->teamMoveTeams()[$id])) {
            $this->teamMoveTeamId = $id;
            $this->teamMoveProblem = null;
            $this->teamMoveError = '';
        }
    }

    public function moveToTeam(): void
    {
        $item = $this->item;
        $teams = $this->teamMoveTeams();

        if ($item === null || ! isset($teams[$this->teamMoveTeamId])) {
            return;
        }

        $this->teamMoveProblem = null;
        $this->teamMoveError = '';

        if ($this->hasUnsentChangesWithin($item['id'])) {
            try {
                app(SunnySyncCoordinator::class)->sync();
            } catch (FatalRequestException) {
                $this->teamMoveProblem = 'offline';

                return;
            } catch (AuthenticationException|RequestException|JsonException|ValidationException|RuntimeException) {
            }

            $this->refreshLocalSyncedData();
            $item = $this->item;

            if ($item === null || $this->hasUnsentChangesWithin($item['id'])) {
                $this->teamMoveProblem = 'unsent';

                return;
            }
        }

        $from = Team::query()->where('server', SunnyStore::server())->find($item['team_id']);
        $to = Team::query()->where('server', SunnyStore::server())->find($this->teamMoveTeamId);

        if (! $from?->slug || $to === null) {
            $this->teamMoveFailed('Sync with Sunny before moving it.');

            return;
        }

        if (Network::status()?->connected === false) {
            $this->teamMoveProblem = 'offline';

            return;
        }

        $parent = $this->parent;

        try {
            $moved = app(MoveItemToTeam::class)->handle($from, $item['id'], $to);
        } catch (FatalRequestException) {
            $this->teamMoveProblem = 'offline';

            return;
        } catch (RequestException $exception) {
            $status = $exception->getResponse()->status();
            $moved = $status === 404 && $this->arrivedInTeam($item['id'], $to->id) ? [$item['id']] : null;
        } catch (AuthenticationException|RuntimeException) {
            $this->teamMoveFailed('Unable to read your saved login. Unlock your device and try again.');

            return;
        } catch (JsonException) {
            $moved = null;
        }

        if (isset($status) && $moved === null) {
            $this->teamMoveFailed(match ($status) {
                403 => 'You no longer have permission to move things in '.($this->teamName() ?? 'this team').'.',
                404 => $item['name'].' isn’t in '.($this->teamName() ?? 'this team').' on Sunny anymore.',
                422 => $teams[$to->id].' isn’t one of your teams anymore.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'Sunny couldn’t move it right now.',
            });

            if ($status !== 404) {
                app(SunnySyncCoordinator::class)->dispatch();
            }

            return;
        }

        if ($moved === null) {
            $this->teamMoveFailed('Sunny didn’t confirm the move.');
            app(SunnySyncCoordinator::class)->dispatch();

            return;
        }

        $this->showTeamMove = false;
        app(SunnySyncCoordinator::class)->dispatch();
        $notice = ['team_id' => $to->id, 'team' => $teams[$to->id], 'item_id' => $item['id']];

        if ($parent === null) {
            Toast::success('Moved '.$item['name'].' to '.$teams[$to->id]);
            $this->back();

            return;
        }

        Cache::put(self::movedToTeamKey($parent['id']), $notice, now()->addMinutes(5));

        if ($this->data('from') === $parent['id']) {
            $this->back();

            return;
        }

        $this->replace('/inventory/'.$parent['id']);
    }

    public function teamName(): ?string
    {
        $teamId = $this->item['team_id'] ?? null;

        return $teamId === null ? null : (app(SunnyTeam::class)->choices()[$teamId] ?? null);
    }

    public function teamMoveSummary(): string
    {
        $count = count($this->children);
        $team = $this->teamName() ?? 'this team';

        return $count === 0
            ? $this->item['name'].' leaves '.$team.'.'
            : $this->item['name'].' and '.trans_choice('the :count thing inside come|the :count things inside come', $count).' along and leave '.$team.'.';
    }

    public function teamMoveDestination(): string
    {
        return $this->item['type'] === ItemType::Location ? 'Goes with the places in your home' : 'Goes to Not in a place yet';
    }

    /**
     * @param  array{team_id?: int|float|string, item_id?: int|float|string}  $data
     */
    #[On(Toast::ACTION_PRESSED, when: ['actionId' => 'switch-team'])]
    public function switchToMovedTeam(array $data): void
    {
        $teamId = is_numeric($data['team_id'] ?? null) ? (int) $data['team_id'] : null;
        $itemId = is_numeric($data['item_id'] ?? null) ? (int) $data['item_id'] : null;

        if ($teamId === null || ! array_key_exists($teamId, app(SunnyTeam::class)->choices())) {
            return;
        }

        app(SunnyTeam::class)->select($teamId);

        $this->replace($itemId !== null && Item::forActiveTeam()->whereKey($itemId)->exists() ? '/inventory/'.$itemId : '/inventory');
    }

    /**
     * @return array{title: string, message: string}
     */
    public function teamMoveNote(): array
    {
        [$title, $message] = match ($this->teamMoveProblem) {
            'offline' => ['You’re offline', 'Moving to another team needs a connection.'],
            'unsent' => ['Still sending your changes', 'Sunny moves what it has, so your latest changes need to reach it first.'],
            default => ['Couldn’t move it', $this->teamMoveError],
        };

        return ['title' => $title, 'message' => $message.' Nothing’s been moved yet.'];
    }

    protected function showMovedToTeam(): void
    {
        $notice = Cache::pull(self::movedToTeamKey((int) $this->param('id')));

        if (is_array($notice)) {
            Toast::success('Moved to '.$notice['team'], action: new ToastAction('Switch', 'switch-team', ['team_id' => $notice['team_id'], 'item_id' => $notice['item_id']]));
        }
    }

    protected function arrivedInTeam(int $itemId, int $teamId): bool
    {
        try {
            app(SunnySyncCoordinator::class)->sync();
        } catch (AuthenticationException|RequestException|FatalRequestException|JsonException|ValidationException|RuntimeException) {
            return false;
        }

        return Item::query()->forCurrentServer()->whereKey($itemId)->value('team_id') === $teamId;
    }

    protected function teamMoveFailed(string $message): void
    {
        $this->teamMoveProblem = 'failed';
        $this->teamMoveError = $message;
    }

    protected function hasUnsentChangesWithin(int $id): bool
    {
        $ids = [$id, ...Inventory::descendantIdsOf($id)];

        return PendingWrite::query()->forCurrentServer()->whereIn('resource', ['items', 'item_photos'])->get()
            ->contains(fn (PendingWrite $write): bool => in_array($write->resource === 'items' ? $write->record_id : ($write->payload['item_id'] ?? null), $ids, true));
    }

    private static function movedToTeamKey(int $parentId): string
    {
        return 'inventory.moved-to-team.'.$parentId;
    }
}
