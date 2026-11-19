<?php

use App\Http\Integrations\Sunny\Requests\CreateTeamRequest;
use App\Http\Integrations\Sunny\Requests\GetTeamInvitationsRequest;
use App\Http\Integrations\Sunny\Requests\LeaveTeamRequest;
use App\Http\Integrations\Sunny\Requests\RemoveTeamMemberRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\Requests\UpdateTeamMemberRequest;
use App\Http\Integrations\Sunny\Requests\UpdateTeamRequest;
use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Recipe;
use App\Models\Team;
use App\NativeComponents\TeamSettings;
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
    Saloon::fake([GetTeamInvitationsRequest::class => fn () => MockResponse::make(['data' => familyInvitations()])]);
});

/**
 * @return list<array{id: int, name: string, initials: string, role: string}>
 */
function familyMembers(): array
{
    return [
        ['id' => 7, 'name' => 'Andy Swick', 'initials' => 'AS', 'role' => 'owner'],
        ['id' => 9, 'name' => 'Ari Swick', 'initials' => 'AS', 'role' => 'member'],
        ['id' => 10, 'name' => 'Grandma Jo', 'initials' => 'GJ', 'role' => 'member'],
        ['id' => 8, 'name' => 'Jamie Swick', 'initials' => 'JS', 'role' => 'admin'],
    ];
}

/**
 * @return list<array<string, string>>
 */
function familyInvitations(): array
{
    return [
        ['code' => 'inv-sam', 'email' => 'sam.ortega@gmail.com', 'role' => 'member', 'expires_at' => now()->addDays(2)->toIso8601String(), 'created_at' => now()->subDay()->toIso8601String()],
        ['code' => 'inv-ray', 'email' => 'ray.swick@outlook.com', 'role' => 'admin', 'expires_at' => now()->addDay()->toIso8601String(), 'created_at' => now()->subDays(2)->toIso8601String()],
    ];
}

/**
 * @return array<string, mixed>
 */
function familyTeam(array $attributes = []): array
{
    return ['id' => 1, 'name' => 'Family', 'slug' => 'family', 'timezone' => 'America/Chicago', 'week_start' => 0, 'is_personal' => false, 'members' => familyMembers(), ...$attributes];
}

function signInAs(int $userId): void
{
    Team::find(1)->update(['timezone' => 'America/Chicago', 'week_start' => 0, 'members' => familyMembers(), 'invitations' => familyInvitations()]);
    DB::table('sunny_accounts')->updateOrInsert(['server' => SunnyStore::server()], ['user_id' => $userId]);
}

function sectionFooter(string $footer): Closure
{
    return fn (array $node): bool => ($node['props']['footer'] ?? null) === $footer;
}

function offlineTeamResponse(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('POST', 'https://sunny.example')), $pending));
}

function listItem(string $ref): Closure
{
    return fn (array $node): bool => ($node['ref'] ?? null) === $ref;
}

it('keeps members, week start and personal teams from sync', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make([
        'teams' => [familyTeam(['week_start' => 1, 'is_personal' => true])],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
        'synced_at' => now()->toIso8601String(),
    ])]);

    app(SunnySync::class)->sync();

    $team = Team::find(1);
    expect($team->week_start)->toBe(1)
        ->and($team->is_personal)->toBeTrue()
        ->and(collect($team->memberList())->pluck('name')->all())->toBe(['Andy Swick', 'Ari Swick', 'Grandma Jo', 'Jamie Swick']);
});

it('opens the picked team from preferences, and offers a new team', function (): void {
    signInAs(7);

    Native::visit('/preferences', platform: 'ios')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'team-settings' && $node['props']['headline'] === 'Family' && $node['props']['supporting'] === '4 members · 2 invitations')
        ->assertSee('New team')
        ->assertAccessible()
        ->tap('team-settings')
        ->assertNavigatedTo('/preferences/team');

    Native::visit('/preferences')->tap('new-team')->assertNavigatedTo('/preferences/teams/create');
});

it('shows the owner everything they can change', function (): void {
    signInAs(7);

    Native::visit('/preferences/team', platform: 'ios')
        ->assertNavTitle('Family')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'team-name' && ($node['props']['trailing_value'] ?? null) === 'Rename')
        ->assertSee('America/Chicago')
        ->assertSee('Sunday')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'member-7' && $node['props']['supporting'] === 'Owner · You')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'member-8' && $node['props']['supporting'] === 'Admin' && ($node['props']['trailing_icon'] ?? null) === 'chevron.right')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'member-7' && ! isset($node['props']['trailing_icon']))
        ->assertElement('list_section', sectionFooter('Tap someone to make them an admin or a member, or to remove them from the team.'))
        ->assertSee('sam.ortega@gmail.com')
        ->assertSee('Member · expires in 2 days')
        ->assertSee('Admin · expires tomorrow')
        ->assertSee('Invite someone')
        ->assertSee('Open team page')
        ->assertMissingElement('list_item', listItem('leave-team'))
        ->assertAccessible();
});

it('lets an admin change the team but not its members', function (): void {
    signInAs(8);

    Native::visit('/preferences/team', platform: 'ios')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'team-name' && ($node['props']['trailing_value'] ?? null) === 'Rename')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'member-9' && ! isset($node['props']['trailing_icon']))
        ->assertElement('list_section', sectionFooter('Only Andy, the owner, can change someone’s role or remove them from the team.'))
        ->assertSee('Invite someone')
        ->assertDontSee('Open team page')
        ->assertSee('Leave Family')
        ->assertElement('list_section', sectionFooter('You’ll lose access to its recipes, inventory, routines and lists. Andy can invite you back.'))
        ->assertAccessible()
        ->tap('member-9')
        ->assertSet('memberId', null);
});

it('shows a member the team without anything to change', function (): void {
    signInAs(9);
    Saloon::fake([UpdateTeamRequest::class => MockResponse::make(['data' => familyTeam()])]);

    Native::visit('/preferences/team', platform: 'ios')
        ->assertMissingElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'team-name' && isset($node['props']['trailing_value']))
        ->assertMissingElement('list_item', listItem('invite-someone'))
        ->assertElement('list_section', sectionFooter('Andy and Jamie can rename the team, change its settings and handle invitations. Only Andy can change roles or remove someone.'))
        ->assertElement('list_section', sectionFooter('They’ll join the team once they accept.'))
        ->assertSee('Leave Family')
        ->assertElement('list_section', sectionFooter('You’ll lose access to its recipes, inventory, routines and lists. Andy or Jamie can invite you back.'))
        ->assertAccessible()
        ->tap('team-name')
        ->assertSet('showRename', false)
        ->call('chooseTimezone', 'Europe/London')
        ->call('chooseWeekStart', 1);

    Saloon::assertNotSent(UpdateTeamRequest::class);
});

it('renames the team', function (): void {
    signInAs(7);
    Saloon::fake([UpdateTeamRequest::class => MockResponse::make(['data' => familyTeam(['name' => 'Swick Household', 'slug' => 'swick-household'])])]);

    Native::visit('/preferences/team')
        ->tap('team-name')
        ->assertSet('showRename', true)
        ->assertSet('newName', 'Family')
        ->input('team-rename-input', 'Swick Household')
        ->tap('team-rename-save')
        ->assertSet('showRename', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Renamed to Swick Household')
        ->assertNavTitle('Swick Household');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof UpdateTeamRequest
        && $request->resolveEndpoint() === '/teams/family'
        && $request->body()->all() === ['name' => 'Swick Household']);
    expect(Team::find(1)->only('name', 'slug'))->toBe(['name' => 'Swick Household', 'slug' => 'swick-household'])
        ->and(app(SunnyTeam::class)->choices())->toBe([1 => 'Swick Household']);
});

it('shows a refused name under the field', function (): void {
    signInAs(8);
    Saloon::fake([UpdateTeamRequest::class => MockResponse::make(['message' => 'That name is reserved.', 'errors' => ['name' => ['That name is reserved.']]], 422)]);

    Native::visit('/preferences/team')
        ->tap('team-name')
        ->input('team-rename-input', 'Settings')
        ->tap('team-rename-save')
        ->assertSet('showRename', true)
        ->assertSet('renameError', 'That name is reserved.');

    expect(Team::find(1)->name)->toBe('Family');
});

it('changes the household timezone and week start', function (): void {
    signInAs(8);
    $saved = familyTeam();
    Saloon::fake([UpdateTeamRequest::class => function (PendingRequest $pending) use (&$saved): MockResponse {
        $saved = [...$saved, ...$pending->body()->all()];

        return MockResponse::make(['data' => $saved]);
    }]);

    Native::visit('/preferences/team')
        ->tap('team-timezone')
        ->assertSet('showTimezone', true)
        ->input('team-timezone-search', 'london')
        ->assertSee('Europe/London')
        ->assertDontSee('America/Chicago ')
        ->tap('team-timezone-Europe/London')
        ->assertSet('showTimezone', false)
        ->tap('team-week-start')
        ->assertSet('showWeekStart', true)
        ->tap('team-week-start-option-1')
        ->assertSet('showWeekStart', false)
        ->assertSee('Monday');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof UpdateTeamRequest && $request->body()->all() === ['timezone' => 'Europe/London']);
    Saloon::assertSent(fn (Request $request): bool => $request instanceof UpdateTeamRequest && $request->body()->all() === ['week_start' => 1]);
    expect(Team::find(1)->only('timezone', 'week_start'))->toBe(['timezone' => 'Europe/London', 'week_start' => 1]);
});

it('lets the owner change a member’s role', function (): void {
    signInAs(7);
    $members = collect(familyMembers())->map(fn (array $member): array => $member['id'] === 9 ? [...$member, 'role' => 'admin'] : $member)->all();
    Saloon::fake([UpdateTeamMemberRequest::class => MockResponse::make(['data' => familyTeam(['members' => $members])])]);

    Native::visit('/preferences/team', platform: 'ios')
        ->tap('member-9')
        ->assertSet('memberId', 9)
        ->assertSee('Member of Family')
        ->assertSee('Can also rename the team, change its settings and invite people.')
        ->assertSee('Remove from Family')
        ->assertAccessible()
        ->tap('member-role-admin')
        ->assertSee('Admin of Family');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof UpdateTeamMemberRequest
        && $request->resolveEndpoint() === '/teams/family/members/9'
        && $request->body()->all() === ['role' => 'admin']);
    expect(Team::find(1)->roleOf(9)->value)->toBe('admin');
});

it('removes a member once the owner confirms', function (): void {
    signInAs(7);
    Saloon::fake([RemoveTeamMemberRequest::class => MockResponse::make([], 204)]);

    Native::visit('/preferences/team')
        ->tap('member-10')
        ->tap('member-remove')
        ->assertNativeNotCalled('Dialog.Alert')
        ->assertSee('Remove Grandma from Family?')
        ->assertSee('Grandma will lose access to the team’s recipes, inventory, routines and lists.')
        ->assertAccessible()
        ->tap('member-remove-keep')
        ->assertDontSee('Remove Grandma from Family?')
        ->tap('member-remove')
        ->tap('member-remove-confirm-button')
        ->assertSet('memberId', null)
        ->assertSet('confirmingRemove', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Removed Grandma Jo')
        ->assertDontSee('Grandma Jo');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof RemoveTeamMemberRequest && $request->resolveEndpoint() === '/teams/family/members/10');
    expect(Team::find(1)->roleOf(10))->toBeNull();
});

it('leaves the current team and switches to another one', function (): void {
    signInAs(9);
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Ari’s Team', 'slug' => 'aris-team', 'is_personal' => true]);
    Saloon::fake([LeaveTeamRequest::class => MockResponse::make([], 204)]);

    Native::visit('/preferences')
        ->tap('team-settings')
        ->follow()
        ->tap('leave-team')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'leave-team'
            && $params['title'] === 'Leave Family?'
            && $params['message'] === 'You’ll lose access to Family. To come back, you’ll need a new invitation.')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Leave', 'id' => 'leave-team'])
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'You left Family')
        ->goBack()
        ->assertSet('activeTeamId', 2)
        ->assertDontSee('Family');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof LeaveTeamRequest && $request->resolveEndpoint() === '/teams/family/leave');
    expect(Team::find(1))->toBeNull()
        ->and(Recipe::where('team_id', 1)->count())->toBe(0)
        ->and(app(SunnyTeam::class)->current()->id)->toBe(2);
});

it('does not offer leaving to the owner or on a personal team', function (): void {
    signInAs(8);
    Team::find(1)->update(['is_personal' => true]);

    Native::visit('/preferences/team')->assertMissingElement('list_item', listItem('leave-team'));
});

it('opens the team page on the website for the owner', function (): void {
    signInAs(7);
    Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/preferences/team')->tap('open-team-page')->assertNoNavigation();

    Native::fakeBridge()->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === app(SunnyConnector::class)->siteUrl().'/settings/teams/family');
});

it('downloads the latest invitations when the team screen opens', function (): void {
    signInAs(9);
    Team::find(1)->update(['invitations' => []]);

    Native::visit('/preferences/team')->assertSee('sam.ortega@gmail.com');

    expect(Team::find(1)->invitations_fetched_at)->not->toBeNull();
});

it('shows the team look-only while offline', function (): void {
    signInAs(7);
    Native::fakeBridge()->respondTo('Network.Status', ['connected' => false]);
    Saloon::fake([UpdateTeamRequest::class => MockResponse::make(['data' => familyTeam()])]);

    Native::visit('/preferences/team', platform: 'ios')
        ->assertSet('offline', true)
        ->assertSee('You’re offline')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'team-name' && ($node['props']['disabled'] ?? false) === true)
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'member-9' && ($node['props']['disabled'] ?? false) === true)
        ->assertElement('list_section', sectionFooter('Roles can be changed and people removed once you’re back online.'))
        ->assertAccessible()
        ->tap('team-name')
        ->assertSet('showRename', false)
        ->tap('member-9')
        ->assertSet('memberId', null)
        ->tap('team-offline')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Still offline. Try again once you’re connected.');

    Saloon::assertNotSent(GetTeamInvitationsRequest::class);
    Saloon::assertNotSent(UpdateTeamRequest::class);
});

it('says when the team was last synced in the phone’s timezone', function (): void {
    signInAs(7);
    $this->travelTo('2026-11-19 03:05:00');
    DB::table('sunny_sync_states')->updateOrInsert(['server' => SunnyStore::server()], ['synced_at' => '2026-11-19T03:05:00+00:00']);
    Native::fakeBridge()
        ->respondTo('Network.Status', ['connected' => false])
        ->respondTo('Device.GetInfo', ['info' => json_encode(['timezone' => 'America/Chicago'])]);

    Native::visit('/preferences/team', platform: 'ios')
        ->assertSee('This is Family as of Today at 9:05 PM.');
});

it('turns look-only when a change can’t reach Sunny', function (): void {
    signInAs(7);
    Saloon::fake([UpdateTeamMemberRequest::class => offlineTeamResponse()]);

    Native::visit('/preferences/team')
        ->tap('member-9')
        ->tap('member-role-admin')
        ->assertSet('offline', true)
        ->assertSet('memberId', null)
        ->assertSee('You’re offline');

    expect(Team::find(1)->roleOf(9)->value)->toBe('member');
});

it('creates a team and switches to it', function (): void {
    signInAs(7);
    Saloon::fake([CreateTeamRequest::class => MockResponse::make(['data' => ['id' => 5, 'name' => 'Lake House', 'slug' => 'lake-house', 'timezone' => 'America/Chicago', 'week_start' => 0, 'is_personal' => false, 'members' => [familyMembers()[0]]]], 201)]);

    Native::visit('/preferences/teams/create', platform: 'ios')
        ->assertNavTitle('New team')
        ->assertSee('You’ll be the owner')
        ->assertSee('We’ll switch you to it')
        ->assertAccessible()
        ->input('create-team-name', 'Lake House')
        ->tap('create-team-submit')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Created Lake House')
        ->assertReplacedWith('/preferences/team');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof CreateTeamRequest && $request->body()->all() === ['name' => 'Lake House']);
    expect(app(SunnyTeam::class)->current()->id)->toBe(5)
        ->and(Team::find(5)->currentRole()->value)->toBe('owner');
});

it('shows why a new team was refused, and keeps the name when offline', function (): void {
    $attempts = 0;
    Saloon::fake([CreateTeamRequest::class => function () use (&$attempts): MockResponse {
        return ++$attempts === 1
            ? MockResponse::make(['message' => 'That name is reserved.', 'errors' => ['name' => ['That name is reserved.']]], 422)
            : offlineTeamResponse();
    }]);

    Native::visit('/preferences/teams/create')
        ->tap('create-team-submit')
        ->assertSet('nameError', 'Enter a name for the team.')
        ->input('create-team-name', 'Settings')
        ->tap('create-team-submit')
        ->assertSet('nameError', 'That name is reserved.')
        ->tap('create-team-submit')
        ->assertSet('offline', true)
        ->assertSee('Creating a team needs a connection. Nothing’s been created yet.')
        ->assertSee('Try again')
        ->assertSet('name', 'Settings')
        ->assertNoNavigation();

    expect(Team::count())->toBe(1);
});

it('keeps the screen on the team that was picked', function (): void {
    signInAs(7);
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Lake Cabin', 'slug' => 'lake-cabin']);
    app(SunnyTeam::class)->select(2);

    Native::visit('/preferences/team')->assertNavTitle('Lake Cabin')->assertSee('No members downloaded yet');

    expect(TeamSettings::listNames(['Andy', 'Jamie', 'Sam']))->toBe('Andy, Jamie and Sam');
});
