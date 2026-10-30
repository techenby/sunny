<?php

use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;

test('invitation notification links to log in with the invitation', function () {
    $user = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($user->currentTeam)->create(['email' => 'test@example.com']);

    $mail = new TeamInvitationNotification($invitation)->toMail(new AnonymousNotifiable);

    expect($mail->actionUrl)->toBe(route('login', ['invitation' => $invitation->code]));
});
