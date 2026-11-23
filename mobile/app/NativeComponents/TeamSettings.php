<?php

namespace App\NativeComponents;

use App\Concerns\CallsSunnyOnline;
use App\Enums\TeamRole;
use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTeams;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Models\Team;
use App\Support\InvitationExpiry;
use App\Support\Offline;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Http\Response;

class TeamSettings extends Component
{
    use CallsSunnyOnline;

    public const array WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public ?int $teamId = null;

    public bool $offline = false;

    public bool $showRename = false;

    public string $newName = '';

    public string $renameError = '';

    public bool $showTimezone = false;

    public string $timezoneSearch = '';

    public ?int $memberId = null;

    public bool $confirmingRemove = false;

    public ?string $invitationsRefreshedAt = null;

    public function mount(): void
    {
        $this->teamId = app(SunnyTeam::class)->current()?->id;
        $this->offline = Network::status()?->connected === false;
        $this->refreshInvitations();
    }

    public function onResume(): void
    {
        if ($this->team() === null) {
            $this->teamId = app(SunnyTeam::class)->current()?->id;
        }
    }

    public function team(): ?Team
    {
        return $this->teamId === null ? null : Team::query()->where('server', SunnyStore::server())->find($this->teamId);
    }

    public function role(): ?TeamRole
    {
        return $this->team()?->currentRole();
    }

    public function canEdit(): bool
    {
        return ! $this->offline && $this->role()?->canUpdateTeam() === true;
    }

    public function canInvite(): bool
    {
        return $this->role()?->canInvite() === true;
    }

    public function canManageMembers(): bool
    {
        return $this->role()?->canManageMembers() === true;
    }

    public function canLeave(): bool
    {
        $team = $this->team();

        return $team !== null && ! $team->is_personal && $this->role() !== null && $this->role() !== TeamRole::Owner;
    }

    /**
     * @return list<array{id: int, name: string, initials: string, role: TeamRole, you: bool, tappable: bool}>
     */
    public function members(): array
    {
        $userId = app(SunnyStore::class)->userId();

        return collect($this->team()?->memberList() ?? [])
            ->sortBy(fn (array $member): string => ($member['role'] === TeamRole::Owner ? '0' : '1').mb_strtolower($member['name']))
            ->map(fn (array $member): array => [
                ...$member,
                'initials' => ($member['initials'] ?? '') !== '' ? $member['initials'] : Preferences::initials($member['name']),
                'you' => $member['id'] === $userId,
                'tappable' => $this->canManageMembers() && $member['role'] !== TeamRole::Owner && $member['id'] !== $userId,
            ])
            ->values()
            ->all();
    }

    public function membersFooter(): string
    {
        $members = collect($this->members());
        $owner = $members->firstWhere('role', TeamRole::Owner);
        $ownerName = $owner === null ? 'The owner' : self::firstName($owner['name']);

        return match (true) {
            $members->isEmpty() => 'Pull down on Summary to download who’s in this team.',
            $this->offline && $this->canManageMembers() => 'Roles can be changed and people removed once you’re back online.',
            $this->canManageMembers() => 'Tap someone to make them an admin or a member, or to remove them from the team.',
            $this->role() === TeamRole::Admin => 'Only '.$ownerName.', the owner, can change someone’s role or remove them from the team.',
            default => self::listNames($members->filter(fn (array $member): bool => $member['role']->canUpdateTeam())->pluck('name')->map(self::firstName(...))->all())
                .' can rename the team, change its settings and handle invitations. Only '.$ownerName.' can change roles or remove someone.',
        };
    }

    /**
     * @return list<array{code: string, email: string, supporting: string}>
     */
    public function invitations(): array
    {
        return collect($this->team()?->invitationList() ?? [])
            ->map(fn (array $invitation): array => [
                'code' => $invitation['code'],
                'email' => $invitation['email'],
                'supporting' => $invitation['role']->label().' · '.lcfirst(InvitationExpiry::label($invitation['expires_at'])),
            ])
            ->all();
    }

    public function weekStart(): string
    {
        return self::WEEKDAYS[$this->team()?->week_start ?? 0] ?? 'Sunday';
    }

    public function syncedAt(): ?string
    {
        $synced = app(SunnyStore::class)->lastSyncedAt();

        return $synced === null ? null : CarbonImmutable::parse($synced)->setTimezone(Device::getTimezone() ?? config('app.timezone'))->calendar();
    }

    public function leaveFooter(): string
    {
        $inviters = collect($this->members())->filter(fn (array $member): bool => $member['role']->canInvite() && ! $member['you'])->pluck('name')->map(self::firstName(...))->all();

        return 'You’ll lose access to its recipes, inventory, routines and lists.'.($inviters === [] ? '' : ' '.self::listNames($inviters, 'or').' can invite you back.');
    }

    /** @return list<string> */
    public function timezones(): array
    {
        $search = mb_strtolower(trim($this->timezoneSearch));

        return collect(DateTimeZone::listIdentifiers())
            ->filter(fn (string $timezone): bool => $search === '' || str_contains(mb_strtolower(str_replace('_', ' ', $timezone)), $search))
            ->values()
            ->all();
    }

    /** @return array{id: int, name: string, initials: string, role: TeamRole}|null */
    public function member(): ?array
    {
        return collect($this->members())->first(fn (array $member): bool => $member['id'] === $this->memberId && $member['tappable']);
    }

    public function retry(): void
    {
        $this->offline = Network::status()?->connected === false;

        if ($this->offline) {
            Toast::error(Offline::message());

            return;
        }

        $this->refreshInvitations();
    }

    public function openRename(): void
    {
        if ($this->canEdit()) {
            $this->newName = $this->team()->name;
            $this->renameError = '';
            $this->showRename = true;
        }
    }

    public function closeRename(): void
    {
        $this->showRename = false;
    }

    public function rename(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $name = trim($this->newName);

        if ($name === '') {
            $this->renameError = 'Enter a name for the team.';

            return;
        }

        if ($name === $this->team()?->name) {
            $this->showRename = false;

            return;
        }

        $this->renameError = '';

        if ($this->updateTeam(['name' => $name])) {
            $this->showRename = false;
            Toast::success('Renamed to '.$name);
        }
    }

    public function openTimezone(): void
    {
        if ($this->canEdit()) {
            $this->timezoneSearch = '';
            $this->showTimezone = true;
        }
    }

    public function closeTimezone(): void
    {
        $this->showTimezone = false;
    }

    public function chooseTimezone(string $timezone): void
    {
        $this->showTimezone = false;

        if ($this->canEdit() && in_array($timezone, DateTimeZone::listIdentifiers(), true) && $timezone !== $this->team()?->timezone) {
            $this->updateTeam(['timezone' => $timezone]);
        }
    }

    public function pickWeekStart(string $day): void
    {
        $index = array_search($day, self::WEEKDAYS, true);

        if ($index !== false) {
            $this->chooseWeekStart($index);
        }
    }

    public function chooseWeekStart(int $day): void
    {
        if ($this->canEdit() && array_key_exists($day, self::WEEKDAYS) && $day !== ($this->team()?->week_start ?? 0)) {
            $this->updateTeam(['week_start' => $day]);
        }
    }

    public function openMember(int $id): void
    {
        if (! $this->offline && collect($this->members())->contains(fn (array $member): bool => $member['id'] === $id && $member['tappable'])) {
            $this->memberId = $id;
            $this->confirmingRemove = false;
        }
    }

    public function closeMember(): void
    {
        $this->memberId = null;
        $this->confirmingRemove = false;
    }

    public function changeRole(string $role): void
    {
        $member = $this->member();
        $role = TeamRole::tryFrom($role);

        if ($member === null || $role === null || ! in_array($role, TeamRole::assignable(), true) || $role === $member['role']) {
            return;
        }

        $this->callSunny(
            fn () => app(SunnyTeams::class)->changeRole($this->team(), $member['id'], $role),
            fn (string $problem, ?Response $response) => $this->failed($problem, $response, 'The server couldn’t change '.self::firstName($member['name']).'’s role right now.'),
        );
    }

    public function confirmRemove(): void
    {
        $this->confirmingRemove = $this->member() !== null;
    }

    public function keepMember(): void
    {
        $this->confirmingRemove = false;
    }

    public function confirmLeave(): void
    {
        if ($this->canLeave()) {
            Dialog::alert('Leave '.$this->team()->name.'?', 'You’ll lose access to '.$this->team()->name.'. To come back, you’ll need a new invitation.', [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Leave', 'style' => 'destructive'],
            ])->id('leave-team')->show();
        }
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'leave-team' && $label === 'Leave') {
            $this->leave();
        }
    }

    public function removeMember(): void
    {
        $member = $this->member();

        if ($member === null || ! $this->confirmingRemove) {
            return;
        }

        $removed = $this->callSunny(
            fn () => app(SunnyTeams::class)->removeMember($this->team(), $member['id']),
            fn (string $problem, ?Response $response) => $this->failed($problem, $response, 'The server couldn’t remove '.self::firstName($member['name']).' right now.'),
        );

        if ($removed) {
            $this->closeMember();
            Toast::success('Removed '.$member['name']);
        }
    }

    public function leave(): void
    {
        $team = $this->team();

        if ($team === null || ! $this->canLeave()) {
            return;
        }

        $left = $this->callSunny(
            fn () => app(SunnyTeams::class)->leave($team),
            fn (string $problem, ?Response $response) => $this->failed($problem, $response, 'The server couldn’t take you out of '.$team->name.' right now.'),
        );

        if ($left) {
            Toast::success('You left '.$team->name);
            $this->back();
        }
    }

    public function openTeamPage(): void
    {
        $team = $this->team();

        if ($team?->slug && ! Browser::inApp(app(SunnyConnector::class)->siteUrl().'/settings/teams/'.rawurlencode($team->slug))) {
            Toast::error('Unable to open the website. Please try again.');
        }
    }

    public function siteHost(): string
    {
        return (string) parse_url(app(SunnyConnector::class)->siteUrl(), PHP_URL_HOST);
    }

    public function render(): View
    {
        return view('native.team-settings');
    }

    public static function firstName(string $name): string
    {
        return (string) str($name)->trim()->before(' ');
    }

    /** @param  list<string>  $names */
    public static function listNames(array $names, string $and = 'and'): string
    {
        return match (count($names)) {
            0 => 'The owner',
            1 => $names[0],
            default => implode(', ', array_slice($names, 0, -1)).' '.$and.' '.end($names),
        };
    }

    /** @param  array{name?: string, timezone?: string, week_start?: int}  $attributes */
    private function updateTeam(array $attributes): bool
    {
        $team = $this->team();

        if ($team === null) {
            return false;
        }

        return $this->callSunny(
            fn () => app(SunnyTeams::class)->update($team, $attributes),
            function (string $problem, ?Response $response) use ($attributes): void {
                if (isset($attributes['name']) && ($message = self::firstError($response, 'name')) !== null) {
                    $this->renameError = $message;

                    return;
                }

                $this->showRename = false;
                $this->failed($problem, $response, 'The server couldn’t save the change right now.');
            },
        );
    }

    private function failed(string $problem, ?Response $response, string $fallback): void
    {
        if ($problem === 'offline') {
            $this->offline = true;
            $this->closeMember();

            return;
        }

        Toast::error(self::failureMessage($problem, $response, $fallback));
    }

    private function refreshInvitations(): void
    {
        $team = $this->team();

        if ($this->offline || $team?->slug === null || $this->role() === null) {
            return;
        }

        try {
            $token = app(SunnyTokenStore::class)->get();
        } catch (RuntimeException) {
            return;
        }

        if ($token === null) {
            return;
        }

        $teamId = $team->id;

        AsyncTask::dispatch(static function () use ($teamId, $token): bool {
            $team = Team::query()->find($teamId);

            if ($team !== null) {
                app(SunnyTeams::class)->refreshTeamInvitations($team, $token);
            }

            return true;
        })->finished(fn () => $this->invitationsRefreshedAt = now()->toIso8601String())->failed(fn () => null);
    }
}
