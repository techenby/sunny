<?php

use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;

test('invitation notification contains accept url', function () {
    $user = User::factory()->withTeam()->create();
    $invitation = TeamInvitation::factory()->for($user->currentTeam)->create(['email' => 'test@example.com']);

    $mail = new TeamInvitationNotification($invitation)->toMail(new AnonymousNotifiable);

    expect($mail->actionUrl)->toContain('invitations/' . $invitation->id . '/accept');
});
