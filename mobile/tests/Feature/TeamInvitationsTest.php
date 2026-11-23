<?php

use App\Http\Integrations\Sunny\Requests\AcceptInvitationRequest;
use App\Http\Integrations\Sunny\Requests\CancelTeamInvitationRequest;
use App\Http\Integrations\Sunny\Requests\CreateTeamInvitationRequest;
use App\Http\Integrations\Sunny\Requests\DeclineInvitationRequest;
use App\Http\Integrations\Sunny\Requests\GetInvitationsRequest;
use App\Http\Integrations\Sunny\Requests\GetTeamInvitationsRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Invitation;
use App\Models\Team;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
});

/**
 * @return list<array<string, string>>
 */
function waitingToJoin(): array
{
    return [
        ['code' => 'inv-jo', 'email' => 'grandma.jo@gmail.com', 'role' => 'member', 'expires_at' => now()->addDays(2)->toIso8601String(), 'created_at' => now()->subDay()->toIso8601String()],
        ['code' => 'inv-lily', 'email' => 'lily.swick@icloud.com', 'role' => 'member', 'expires_at' => now()->addDay()->toIso8601String(), 'created_at' => now()->subDays(2)->toIso8601String()],
    ];
}

function signInToFamilyAs(int $userId): void
{
    Team::find(1)->update([
        'members' => [
            ['id' => 7, 'name' => 'Andy Swick', 'initials' => 'AS', 'role' => 'owner'],
            ['id' => 9, 'name' => 'Ari Swick', 'initials' => 'AS', 'role' => 'member'],
            ['id' => 8, 'name' => 'Jamie Swick', 'initials' => 'JS', 'role' => 'admin'],
        ],
        'invitations' => waitingToJoin(),
        'invitations_fetched_at' => now()->setTime(9, 32),
    ]);
    DB::table('sunny_accounts')->updateOrInsert(['server' => SunnyStore::server()], ['user_id' => $userId]);
}

function invitedToMillerCabin(): Invitation
{
    return Invitation::create([
        'code' => 'miller-code',
        'server' => SunnyStore::server(),
        'team_name' => 'Miller Cabin',
        'inviter_name' => 'Pat Miller',
        'role' => 'member',
        'expires_at' => now()->addDays(2),
        'created_at' => now()->subDay(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function millerCabin(): array
{
    return ['id' => 3, 'name' => 'Miller Cabin', 'slug' => 'miller-cabin', 'timezone' => 'America/Denver', 'week_start' => 1, 'is_personal' => false, 'members' => [
        ['id' => 20, 'name' => 'Pat Miller', 'initials' => 'PM', 'role' => 'owner'],
        ['id' => 7, 'name' => 'Andy Swick', 'initials' => 'AS', 'role' => 'member'],
    ]];
}

function cannotConnect(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('POST', 'https://sunny.example')), $pending));
}

it('downloads my invitations with each sync', function (): void {
    Saloon::fake([
        SyncRequest::class => MockResponse::make([
            'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
            'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
            'synced_at' => now()->toIso8601String(),
        ]),
        GetInvitationsRequest::class => MockResponse::make(['data' => [[
            'code' => 'miller-code', 'email' => 'andy@swick.family', 'role' => 'member',
            'team' => ['id' => 3, 'name' => 'Miller Cabin', 'slug' => 'miller-cabin'],
            'inviter' => ['id' => 20, 'name' => 'Pat Miller'],
            'expires_at' => now()->addDays(2)->toIso8601String(), 'created_at' => now()->subDay()->toIso8601String(),
        ]]]),
    ]);

    app(SunnySyncCoordinator::class)->sync();

    expect(Invitation::sole()->only('code', 'team_name', 'inviter_name'))->toBe(['code' => 'miller-code', 'team_name' => 'Miller Cabin', 'inviter_name' => 'Pat Miller']);
});

it('still syncs when Sunny can’t list my invitations', function (): void {
    invitedToMillerCabin();
    Saloon::fake([
        SyncRequest::class => MockResponse::make([
            'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
            'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
            'synced_at' => now()->toIso8601String(),
        ]),
        GetInvitationsRequest::class => MockResponse::make([], 404),
    ]);

    expect(app(SunnySyncCoordinator::class)->sync())->toBeTrue()
        ->and(Invitation::count())->toBe(1);
});

it('accepts an invitation from Summary', function (): void {
    invitedToMillerCabin();
    Saloon::fake([AcceptInvitationRequest::class => MockResponse::make(['data' => millerCabin()])]);
    DB::table('sunny_accounts')->updateOrInsert(['server' => SunnyStore::server()], ['user_id' => 7]);

    Native::visit('/dashboard', platform: 'ios')
        ->assertSee('Pat Miller invited you to join Miller Cabin')
        ->assertSee('Sent 1 day ago · Expires in 2 days')
        ->assertSee('Join Miller Cabin')
        ->assertAccessible()
        ->tap('dashboard-accept-miller-code')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'You joined Miller Cabin. Switch to it in Settings.')
        ->assertDontSee('Pat Miller invited you');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof AcceptInvitationRequest && $request->resolveEndpoint() === '/invitations/miller-code/accept');
    expect(Invitation::count())->toBe(0)
        ->and(app(SunnyTeam::class)->choices())->toBe([1 => 'Family', 3 => 'Miller Cabin'])
        ->and(app(SunnyTeam::class)->current()->id)->toBe(1)
        ->and(Team::find(3)->currentRole()->value)->toBe('member');
});

it('declines an invitation from Summary', function (): void {
    invitedToMillerCabin();
    Saloon::fake([DeclineInvitationRequest::class => MockResponse::make([], 204)]);

    Native::visit('/dashboard')
        ->tap('dashboard-decline-miller-code')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Declined the invitation to Miller Cabin')
        ->assertDontSee('Miller Cabin');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof DeclineInvitationRequest && $request->resolveEndpoint() === '/invitations/miller-code/decline');
    expect(Invitation::count())->toBe(0)->and(Team::find(3))->toBeNull();
});

it('accepts and declines invitations in Preferences', function (): void {
    $this->travelTo(now()->setTime(12, 0));
    invitedToMillerCabin();
    Invitation::create(['code' => 'lake-code', 'server' => SunnyStore::server(), 'team_name' => 'Lake House', 'inviter_name' => 'Sam Lee', 'role' => 'admin', 'expires_at' => now()->addHours(3), 'created_at' => now()]);
    Saloon::fake([
        AcceptInvitationRequest::class => MockResponse::make(['data' => millerCabin()]),
        DeclineInvitationRequest::class => MockResponse::make([], 204),
    ]);

    Native::visit('/preferences', platform: 'ios')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'invitation-miller-code' && $node['props']['a11y_label'] === 'Pat Miller invited you to join Miller Cabin, Expires in 2 days')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'invitation-lake-code' && str_ends_with($node['props']['a11y_label'], ', Expires today'))
        ->assertAccessible()
        ->tap('accept-invitation-miller-code')
        ->assertSee('Miller Cabin')
        ->assertMissingElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'invitation-miller-code')
        ->tap('decline-invitation-lake-code')
        ->assertDontSee('Lake House');

    expect(Invitation::count())->toBe(0);
});

it('forgets an invitation Sunny no longer has', function (): void {
    invitedToMillerCabin();
    Saloon::fake([AcceptInvitationRequest::class => MockResponse::make(['message' => 'This invitation has expired.', 'errors' => ['invitation' => ['This invitation has expired.']]], 422)]);

    Native::visit('/dashboard')
        ->tap('dashboard-accept-miller-code')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'This invitation has expired.')
        ->assertDontSee('Miller Cabin');

    expect(Invitation::count())->toBe(0)->and(Team::find(3))->toBeNull();
});

it('keeps an invitation to answer later when offline', function (): void {
    invitedToMillerCabin();
    Saloon::fake([AcceptInvitationRequest::class => cannotConnect()]);

    Native::visit('/dashboard')
        ->tap('dashboard-accept-miller-code')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'You’re offline. Joining a team needs a connection.')
        ->assertSee('Join Miller Cabin');

    expect(Invitation::count())->toBe(1);
});

it('opens the invite screen from the team screen', function (): void {
    signInToFamilyAs(7);
    Saloon::fake([GetTeamInvitationsRequest::class => MockResponse::make(['data' => waitingToJoin()])]);

    Native::visit('/preferences/team')
        ->tap('invite-someone')
        ->assertNavigatedTo('/preferences/team/invite');
});

it('sends an invitation', function (): void {
    signInToFamilyAs(8);
    $sam = ['code' => 'inv-sam', 'email' => 'sam.swick@gmail.com', 'role' => 'admin', 'expires_at' => now()->addDays(3)->toIso8601String(), 'created_at' => now()->toIso8601String()];
    Saloon::fake([
        GetTeamInvitationsRequest::class => MockResponse::make(['data' => waitingToJoin()]),
        CreateTeamInvitationRequest::class => MockResponse::make(['data' => $sam], 201),
    ]);

    Native::visit('/preferences/team/invite', platform: 'ios')
        ->assertNavTitle('Invite your family')
        ->assertSee('Share recipes, inventory, routines and lists with the people you live with.')
        ->assertSee('grandma.jo@gmail.com')
        ->assertSee('Expires in 2 days')
        ->assertSee('Expires tomorrow')
        ->assertSee('We’ll email them a link that lasts 3 days.')
        ->assertSee('Cancel an invitation and its link stops working.')
        ->assertAccessible()
        ->input('invite-email', 'sam.swick@gmail.com')
        ->tap('invite-role-admin')
        ->assertSet('role', 'admin')
        ->tap('invite-send')
        ->assertSee('Invitation sent')
        ->assertSee('sam.swick@gmail.com can join as an admin.')
        ->assertSet('email', '')
        ->assertSet('role', 'member')
        ->assertSee('Expires in 3 days');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof CreateTeamInvitationRequest
        && $request->resolveEndpoint() === '/teams/family/invitations'
        && $request->body()->all() === ['email' => 'sam.swick@gmail.com', 'role' => 'admin']);
    expect(collect(Team::find(1)->invitationList())->pluck('email')->all())->toBe(['sam.swick@gmail.com', 'grandma.jo@gmail.com', 'lily.swick@icloud.com']);
});

it('shows an existing invitation under the email field', function (): void {
    signInToFamilyAs(7);
    Saloon::fake([
        GetTeamInvitationsRequest::class => MockResponse::make(['data' => waitingToJoin()]),
        CreateTeamInvitationRequest::class => MockResponse::make(['message' => 'An invitation has already been sent to this email address.', 'errors' => ['email' => ['An invitation has already been sent to this email address.']]], 422),
    ]);

    Native::visit('/preferences/team/invite')
        ->input('invite-email', 'Grandma.Jo@gmail.com')
        ->tap('invite-send')
        ->assertSet('emailError', 'An invitation has already been sent to this email address.')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'invite-email' && ($node['props']['is_error'] ?? null) === true)
        ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'waiting-inv-jo')
        ->assertSet('sent', null);

    expect(collect(Team::find(1)->invitationList())->pluck('email')->all())->toBe(['grandma.jo@gmail.com', 'lily.swick@icloud.com']);
});

it('checks the email before sending', function (): void {
    signInToFamilyAs(7);
    Saloon::fake([GetTeamInvitationsRequest::class => MockResponse::make(['data' => waitingToJoin()])]);

    Native::visit('/preferences/team/invite')
        ->input('invite-email', 'grandma')
        ->tap('invite-send')
        ->assertSet('emailError', 'Enter a valid email address.');

    Saloon::assertNotSent(CreateTeamInvitationRequest::class);
});

it('sends nothing while offline and lets the person try again', function (): void {
    signInToFamilyAs(7);
    Saloon::fake([
        GetTeamInvitationsRequest::class => MockResponse::make(['data' => waitingToJoin()]),
        CreateTeamInvitationRequest::class => cannotConnect(),
    ]);

    Native::visit('/preferences/team/invite')
        ->input('invite-email', 'sam.swick@gmail.com')
        ->tap('invite-send')
        ->assertSet('offline', true)
        ->assertSee('Sending an invitation needs a connection, since Sunny emails it right away. Nothing’s been sent to sam.swick@gmail.com yet.')
        ->assertSee('Try again')
        ->assertSee('Last updated at')
        ->assertSet('email', 'sam.swick@gmail.com');
});

it('cancels an invitation once confirmed', function (): void {
    signInToFamilyAs(7);
    Saloon::fake([
        GetTeamInvitationsRequest::class => MockResponse::make(['data' => waitingToJoin()]),
        CancelTeamInvitationRequest::class => MockResponse::make([], 204),
    ]);

    Native::visit('/preferences/team/invite')
        ->tap('cancel-invitation-inv-jo')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'cancel-invitation'
            && $params['title'] === 'Cancel invitation?'
            && $params['message'] === 'The link sent to grandma.jo@gmail.com will stop working. They’d need a new invitation to join Family.')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Keep', 'id' => 'cancel-invitation'])
        ->assertSee('grandma.jo@gmail.com')
        ->tap('cancel-invitation-inv-jo')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Cancel It', 'id' => 'cancel-invitation'])
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Cancelled the invitation to grandma.jo@gmail.com')
        ->assertDontSee('grandma.jo@gmail.com');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof CancelTeamInvitationRequest && $request->resolveEndpoint() === '/teams/family/invitations/inv-jo');
});

it('shows members who can’t invite who to ask', function (): void {
    signInToFamilyAs(9);
    Saloon::fake([GetTeamInvitationsRequest::class => MockResponse::make(['data' => waitingToJoin()])]);

    Native::visit('/preferences/team/invite', platform: 'ios')
        ->assertSee('Ask Andy or Jamie')
        ->assertSee('Only the owner and admins can invite people to Family or cancel invitations.')
        ->assertSee('grandma.jo@gmail.com')
        ->assertSee('They’ll join the team once they accept.')
        ->assertMissingElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'invite-email')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'cancel-invitation-inv-jo')
        ->assertAccessible()
        ->call('confirmCancel', 'inv-jo')
        ->assertNativeNotCalled('Dialog.Alert');

    Saloon::assertNotSent(CancelTeamInvitationRequest::class);
});

it('counts the days until an invitation expires on the phone’s calendar', function (string $now, string $expiresAt, ?string $timezone, string $label): void {
    $this->travelTo($now);
    Native::fakeBridge()->respondTo('Device.GetInfo', ['info' => json_encode(['timezone' => $timezone])]);
    Invitation::create(['code' => 'lake-code', 'server' => SunnyStore::server(), 'team_name' => 'Lake House', 'inviter_name' => 'Sam Lee', 'role' => 'admin', 'expires_at' => $expiresAt, 'created_at' => now()]);

    Native::visit('/preferences', platform: 'ios')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'invitation-lake-code' && str_ends_with($node['props']['a11y_label'], ', '.$label));
})->with([
    'this evening, before midnight UTC' => ['2026-11-19 21:45:00', '2026-11-20T00:45:00+00:00', 'America/Chicago', 'Expires today'],
    'tomorrow morning, after midnight UTC' => ['2026-11-20 01:00:00', '2026-11-20T15:00:00+00:00', 'America/Chicago', 'Expires tomorrow'],
    'late tonight, east of UTC' => ['2026-11-19 12:00:00', '2026-11-19T14:30:00+00:00', 'Asia/Tokyo', 'Expires today'],
    'early tomorrow, east of UTC' => ['2026-11-19 12:00:00', '2026-11-19T16:00:00+00:00', 'Asia/Tokyo', 'Expires tomorrow'],
    'a phone that doesn’t say its timezone' => ['2026-11-19 21:45:00', '2026-11-20T00:45:00+00:00', null, 'Expires tomorrow'],
]);
