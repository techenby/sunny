<?php

use App\Enums\TeamRole;
use App\Models\KioskDevice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withCookie;

test('guest visiting /kiosk gets a QR code and pairing row', function (): void {
    get('/kiosk')
        ->assertOk()
        ->assertCookie('kiosk_device_uuid');

    expect(KioskDevice::query()->first())->not->toBeNull()
        ->pairing_code->toHaveLength(8)
        ->paired_at->toBeNull()
        ->expires_at->not->toBeNull();
});

test('the same browser keeps its pairing row on repeat visits', function (): void {
    expect(KioskDevice::query()->count())->toBe(0);

    // generate device code
    get('/kiosk');

    $device = KioskDevice::query()->first();
    $cookie = Cookie::queued('kiosk_device_uuid')->getValue();
    expect($device->uuid)->toBe($cookie);

    // visit again and see same code
    withCookie('kiosk_device_uuid', $cookie)
        ->get('/kiosk')
        ->assertOk()
        ->assertSee($device->pairing_code);

    expect(KioskDevice::query()->count())->toBe(1);
});

test('phone confirm route requires authentication', function (): void {
    $device = KioskDevice::factory()->pending()->create();

    get(route('kiosk.pair', ['code' => $device->pairing_code]))
        ->assertRedirect(route('login'));
});

test('phone visiting an expired code sees the expired view', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->expired()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.pair', ['code' => $device->pairing_code])
        ->assertSet('expired', true)
        ->assertSee(__('Pairing code expired'));
});

test('phone visiting an unknown code sees the expired view', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.pair', ['code' => 'NOPECODE'])
        ->assertSet('expired', true);
});

test('phone can approve a pending device and mark it paired', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->pending()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.pair', ['code' => $device->pairing_code])
        ->set('name', 'Kitchen TV')
        ->set('teamId', $user->currentTeam->id)
        ->call('approve')
        ->assertSet('paired', true);

    expect($device->refresh())
        ->paired_at->not->toBeNull()
        ->expires_at->toBeNull()
        ->pairing_code->toBeNull()
        ->user_id->toBe($user->id)
        ->team_id->toBe($user->currentTeam->id)
        ->name->toBe('Kitchen TV');
});

test('phone cannot approve a team they are not a member of', function (): void {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $device = KioskDevice::factory()->pending()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.pair', ['code' => $device->pairing_code])
        ->set('teamId', $otherTeam->id)
        ->call('approve')
        ->assertHasErrors(['teamId']);

    expect($device->fresh())
        ->paired_at->toBeNull();
});

test('once a phone approves the device poll logs in and redirects', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->pending()->create();

    $component = Livewire::withCookie('kiosk_device_uuid', $device->uuid)
        ->test('pages::kiosk.index');

    KioskDevice::query()->whereKey($device->id)->update([
        'user_id' => $user->id,
        'team_id' => $user->currentTeam->id,
        'paired_at' => now(),
        'expires_at' => null,
        'pairing_code' => null,
    ]);

    $component->call('check')
        ->assertRedirect(route('kiosk.calendar', ['current_team' => $user->currentTeam->slug]));

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id)
        ->and(session('kiosk_device_id'))->toBe($device->id);
});

test('concurrent approve only succeeds once', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $device = KioskDevice::factory()->pending()->create();

    Livewire::actingAs($userA)
        ->test('pages::kiosk.pair', ['code' => $device->pairing_code])
        ->set('teamId', $userA->currentTeam->id)
        ->call('approve')
        ->assertSet('paired', true);

    Livewire::actingAs($userB)
        ->test('pages::kiosk.pair', ['code' => $device->pairing_code])
        ->set('teamId', $userB->currentTeam->id)
        ->call('approve')
        ->assertSet('expired', true)
        ->assertSet('paired', false);

    expect($device->refresh())
        ->user_id->toBe($userA->id)
        ->team_id->toBe($userA->currentTeam->id);
});

test('approve fails if pairing_code rotates between mount and approve', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->pending()->create();

    $phone = Livewire::actingAs($user)
        ->test('pages::kiosk.pair', ['code' => $device->pairing_code])
        ->set('teamId', $user->currentTeam->id);

    KioskDevice::query()->whereKey($device->id)->update([
        'pairing_code' => KioskDevice::generatePairingCode(),
    ]);

    $phone
        ->call('approve')
        ->assertSet('expired', true);

    expect($device->fresh())
        ->paired_at->toBeNull();
});

test('paired kiosk session cannot access dashboard', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired($user, $user->currentTeam)->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('paired kiosk session can access kiosk pages', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired($user, $user->currentTeam)->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route('kiosk.calendar'))
        ->assertOk();
});

test('a forgotten kiosk is signed out and sent back to pairing', function (string $route): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired($user, $user->currentTeam)->create();
    $device->delete();

    actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route($route))
        ->assertRedirect(route('kiosk.index'))
        ->assertSessionMissing('kiosk_device_id');

    $this->assertGuest();
})->with(['kiosk.calendar', 'dashboard']);

test('a forgotten kiosk\'s Livewire requests are rejected as unauthenticated', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => 99])
        ->withHeader('X-Livewire', 'true')
        ->get(route('kiosk.calendar'))
        ->assertUnauthorized();

    $this->assertGuest();
});

test('a kiosk that lost its session goes back to pairing instead of the login page', function (): void {
    withCookie(KioskDevice::COOKIE_NAME, 'some-uuid')
        ->get('/straw-hats/kiosk/calendar')
        ->assertRedirect(route('kiosk.index'));
});

test('guests are sent to the login page for kiosk configuration', function (): void {
    withCookie(KioskDevice::COOKIE_NAME, 'some-uuid')
        ->get('/straw-hats/kiosk/configure/settings')
        ->assertRedirect(route('login'));
});

test('guests without a kiosk cookie are sent to the login page', function (): void {
    get('/straw-hats/kiosk/calendar')
        ->assertRedirect(route('login'));
});

test('kiosk session cannot use a device paired by another user', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired(team: $user->currentTeam)->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route('kiosk.calendar'))
        ->assertRedirect(route('kiosk.index'));

    $this->assertGuest();
});

test('kiosk session cannot reach kiosk pages for another of the user\'s teams', function (string $route): void {
    $user = User::factory()->create();
    $teamA = $user->currentTeam;
    $teamB = Team::factory()->create();
    $user->teams()->attach($teamB, ['role' => TeamRole::Member]);
    $device = KioskDevice::factory()->paired($user, $teamA)->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route($route, ['current_team' => $teamB->slug]))
        ->assertForbidden();

    expect($user->fresh()->current_team_id)->toBe($teamA->id);
})->with(['kiosk.lists', 'kiosk.calendar', 'kiosk.routines', 'kiosk.meal-planning']);

test('kiosk session cannot reach the configure pages', function (string $route): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired($user, $user->currentTeam)->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route($route))
        ->assertForbidden();
})->with(['kiosk.configure.preview', 'kiosk.configure.calendar', 'kiosk.configure.settings']);

test('kiosk session cannot pair another device', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired($user, $user->currentTeam)->create();
    $pending = KioskDevice::factory()->pending()->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route('kiosk.pair', ['code' => $pending->pairing_code]))
        ->assertForbidden();
});

test('paired kiosk session can access root /kiosk after its device row is deleted', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->withSession(['kiosk_device_id' => 99])
        ->get('/kiosk')
        ->assertOk()
        ->assertSessionMissing('kiosk_device_id');

    $this->assertGuest();
});

test('paired kiosk session can hit livewire update endpoint', function (): void {
    $user = User::factory()->create();
    $updatePath = EndpointResolver::updatePath();

    $response = $this->actingAs($user)->withSession(['kiosk_device_id' => 99])
        ->post($updatePath, []);

    expect($response->status())->not->toBe(403);
});

test('normal user session is not restricted', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('deleting a paired device returns it to QR on next visit', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired()->create([
        'uuid' => 'fixed-uuid',
        'user_id' => $user->id,
        'team_id' => $user->currentTeam->id,
    ]);

    $device->delete();

    withCookie('kiosk_device_uuid', 'fixed-uuid')
        ->get('/kiosk')
        ->assertOk();

    expect(KioskDevice::query()->count())->toBe(1);
    expect(KioskDevice::query()->first())
        ->uuid->not->toBe('fixed-uuid')
        ->paired_at->toBeNull();
});

test('session id rotates after pair-completion login', function (): void {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->pending()->create();

    session()->put('marker', 'before-pair');
    $sessionIdBefore = session()->getId();

    $component = Livewire::withCookie('kiosk_device_uuid', $device->uuid)
        ->test('pages::kiosk.index');

    KioskDevice::query()->whereKey($device->id)->update([
        'user_id' => $user->id,
        'team_id' => $user->currentTeam->id,
        'paired_at' => now(),
        'expires_at' => null,
        'pairing_code' => null,
    ]);

    $component->call('check');

    expect(session()->getId())->not->toBe($sessionIdBefore);
});
