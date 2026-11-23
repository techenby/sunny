<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyTeams;
use App\Models\Invitation;
use App\NativeComponents\Preferences;
use App\Support\InvitationExpiry;
use App\Support\Offline;
use App\Support\Toast;
use Native\Mobile\Attributes\Computed;
use Saloon\Http\Response;

trait AnswersInvitations
{
    use CallsSunnyOnline;

    /**
     * @return list<array{code: string, team: string, inviter: string|null, initials: string, expires: string, sent: string|null}>
     */
    #[Computed]
    public function invitations(): array
    {
        return Invitation::query()->pending()->get()
            ->map(fn (Invitation $invitation): array => [
                'code' => $invitation->code,
                'team' => $invitation->team_name,
                'inviter' => $invitation->inviter_name,
                'initials' => Preferences::initials($invitation->inviter_name ?? $invitation->team_name),
                'expires' => InvitationExpiry::label($invitation->expires_at),
                'sent' => $invitation->created_at?->diffForHumans(),
            ])
            ->all();
    }

    public function acceptInvitation(string $code): void
    {
        $invitation = Invitation::query()->pending()->find($code);

        if ($invitation === null) {
            return;
        }

        $joined = $this->callSunny(
            fn () => app(SunnyTeams::class)->accept($invitation),
            fn (string $problem, ?Response $response) => $this->invitationFailed($invitation, $problem, $response),
        );

        if ($joined) {
            Toast::success('You joined '.$invitation->team_name.'. Switch to it in Settings.');
        }

        $this->invitationsChanged();
    }

    public function declineInvitation(string $code): void
    {
        $invitation = Invitation::query()->pending()->find($code);

        if ($invitation === null) {
            return;
        }

        $declined = $this->callSunny(
            fn () => app(SunnyTeams::class)->decline($invitation),
            fn (string $problem, ?Response $response) => $this->invitationFailed($invitation, $problem, $response),
        );

        if ($declined) {
            Toast::success('Declined the invitation to '.$invitation->team_name);
        }

        $this->invitationsChanged();
    }

    protected function invitationsChanged(): void
    {
        unset($this->invitations);
    }

    private function invitationFailed(Invitation $invitation, string $problem, ?Response $response): void
    {
        if (in_array($response?->status(), [404, 422], true)) {
            $invitation->delete();
            Toast::error(self::firstError($response) ?? 'That invitation isn’t there anymore.');

            return;
        }

        Toast::error($problem === 'offline' ? Offline::message() : self::failureMessage($problem, $response, 'The server couldn’t answer the invitation right now. Please try again.'));
    }
}
