<?php

namespace App\NativeComponents;

use App\Concerns\CallsSunnyOnline;
use App\Concerns\NeedsConnection;
use App\Enums\TeamRole;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTeams;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Models\Team;
use App\Support\InvitationExpiry;
use App\Support\Offline;
use App\Support\Toast;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Saloon\Http\Response;

class InviteMember extends Component
{
    use CallsSunnyOnline;
    use NeedsConnection;

    public ?int $teamId = null;

    public string $email = '';

    public string $role = 'member';

    public bool $offline = false;

    public string $emailError = '';

    public string $error = '';

    /** @var array{email: string, role: string}|null */
    public ?array $sent = null;

    public ?string $highlightedEmail = null;

    public ?string $pendingCancel = null;

    public ?string $refreshedAt = null;

    public function mount(): void
    {
        $this->teamId = app(SunnyTeam::class)->current()?->id;
        $this->refresh();
    }

    public function team(): ?Team
    {
        return $this->teamId === null ? null : Team::query()->where('server', SunnyStore::server())->find($this->teamId);
    }

    public function canInvite(): bool
    {
        return $this->team()?->currentRole()?->canInvite() === true;
    }

    /**
     * @return list<array{code: string, email: string, role: TeamRole, expires: string, soon: bool, highlighted: bool}>
     */
    public function invitations(): array
    {
        return collect($this->team()?->invitationList() ?? [])
            ->map(fn (array $invitation): array => [
                ...$invitation,
                'expires' => InvitationExpiry::label($invitation['expires_at']),
                'soon' => InvitationExpiry::isSoon($invitation['expires_at']),
                'highlighted' => $this->highlightedEmail !== null && mb_strtolower($invitation['email']) === mb_strtolower($this->highlightedEmail),
            ])
            ->all();
    }

    /**
     * @return list<array{name: string, initials: string, role: TeamRole}>
     */
    public function inviters(): array
    {
        return collect($this->team()?->memberList() ?? [])
            ->filter(fn (array $member): bool => $member['role']->canInvite())
            ->sortBy(fn (array $member): string => ($member['role'] === TeamRole::Owner ? '0' : '1').mb_strtolower($member['name']))
            ->map(fn (array $member): array => [...$member, 'initials' => ($member['initials'] ?? '') !== '' ? $member['initials'] : Preferences::initials($member['name'])])
            ->values()
            ->all();
    }

    public function updatedAt(): ?string
    {
        return $this->team()?->invitations_fetched_at?->setTimezone(Device::getTimezone() ?? config('app.timezone'))->format('g:i A');
    }

    public function chooseRole(string $role): void
    {
        if (in_array(TeamRole::tryFrom($role), TeamRole::assignable(), true)) {
            $this->role = $role;
        }
    }

    public function send(): void
    {
        $team = $this->team();
        $email = trim($this->email);
        $this->emailError = '';
        $this->error = '';
        $this->highlightedEmail = null;

        if ($team === null || ! $this->canInvite()) {
            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->emailError = 'Enter a valid email address.';

            return;
        }

        $role = TeamRole::tryFrom($this->role) ?? TeamRole::Member;
        $this->offline = false;

        $sent = $this->callSunny(
            fn () => app(SunnyTeams::class)->invite($team, $email, $role),
            function (string $problem, ?Response $response) use ($email): void {
                if ($problem === 'offline') {
                    $this->offline = true;

                    return;
                }

                if (($message = self::firstError($response, 'email')) !== null) {
                    $this->emailError = $message;
                    $this->highlightedEmail = $email;

                    return;
                }

                $this->error = self::failureMessage($problem, $response, 'The server couldn’t send the invitation right now.');
            },
        );

        if ($sent) {
            $this->sent = ['email' => $email, 'role' => $role->value];
            $this->email = '';
            $this->role = TeamRole::Member->value;
        }
    }

    public function confirmCancel(string $code): void
    {
        $invitation = collect($this->team()?->invitationList() ?? [])->firstWhere('code', $code);

        if ($invitation === null || ! $this->canInvite()) {
            return;
        }

        $this->pendingCancel = $code;

        Dialog::alert('Cancel invitation?', 'The link sent to '.$invitation['email'].' will stop working. They’d need a new invitation to join '.$this->team()->name.'.', [
            ['label' => 'Keep', 'style' => 'cancel'],
            ['label' => 'Cancel It', 'style' => 'destructive'],
        ])->id('cancel-invitation')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'cancel-invitation' && $label === 'Cancel It' && $this->pendingCancel !== null) {
            $this->cancel($this->pendingCancel);
        }

        $this->pendingCancel = null;
    }

    public function cancel(string $code): void
    {
        $team = $this->team();
        $invitation = collect($team?->invitationList() ?? [])->firstWhere('code', $code);

        if ($team === null || $invitation === null || ! $this->canInvite()) {
            return;
        }

        $cancelled = $this->callSunny(
            fn () => app(SunnyTeams::class)->cancelInvitation($team, $code),
            function (string $problem, ?Response $response) use ($team, $code): void {
                if ($problem === 'offline') {
                    Toast::error(Offline::message());

                    return;
                }

                if ($response?->status() === 404) {
                    app(SunnyTeams::class)->forgetTeamInvitation($team, $code);

                    return;
                }

                Toast::error(self::failureMessage($problem, $response, 'The server couldn’t cancel the invitation right now.'));
            },
        );

        if ($cancelled) {
            if ($this->sent !== null && $this->sent['email'] === $invitation['email']) {
                $this->sent = null;
            }

            Toast::success('Cancelled the invitation to '.$invitation['email']);
        }
    }

    public function render(): View
    {
        return view('native.invite-member');
    }

    private function refresh(): void
    {
        $team = $this->team();
        if ($this->disconnected || $team?->slug === null || $team->currentRole() === null) {
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
        })->finished(fn () => $this->refreshedAt = now()->toIso8601String())->failed(fn () => null);
    }
}
