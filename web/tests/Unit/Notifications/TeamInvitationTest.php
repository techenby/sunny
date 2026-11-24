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

test('a team or inviter name cannot put a link or formatting into the email', function () {
    $user = User::factory()->create(['name' => '**Admin** [reset your password](https://evil.example/a)']);
    $user->currentTeam->update(['name' => '[Click here](https://evil.example/b)']);
    $invitation = TeamInvitation::factory()->for($user->currentTeam)->create(['email' => 'test@example.com', 'invited_by' => $user->id]);

    $html = (string) new TeamInvitationNotification($invitation)->toMail(new AnonymousNotifiable)->render();

    expect($html)->not->toContain('href="https://evil.example')
        ->not->toContain('<strong>Admin</strong>')
        ->toContain('[Click here](https://evil.example/b)');
});
