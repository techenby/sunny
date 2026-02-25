<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TeamInvitation $invitation) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $teamName = $this->invitation->team->name;
        $expiresAt = now()->addDays(7);
        $acceptUrl = URL::temporarySignedRoute('invitation.accept', $expiresAt, $this->invitation);

        return (new MailMessage)
            ->subject(__("You've been invited to join :team", ['team' => $teamName]))
            ->line(__("You've been invited to join the :team team.", ['team' => $teamName]))
            ->action(__('Accept Invitation'), $acceptUrl)
            ->line(__('This invitation will expire on :date.', ['date' => $expiresAt->toDayDateTimeString()]))
            ->line(__('If you did not expect to receive this invitation, you may discard this email.'));
    }
}
