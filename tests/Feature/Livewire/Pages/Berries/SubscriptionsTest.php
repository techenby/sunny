<?php

use App\BillingFrequency;
use App\Livewire\Pages\Berries\Subscriptions;
use App\Livewire\Pages\Collections\Lego;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/berries/subscriptions')
        ->assertOk()
        ->assertSee('Subscriptions');
});

test('can view component', function () {
    Subscription::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Netflix'],
            ['name' => 'Crunchyroll'],
            ['name' => 'Disney+'],
        ))
        ->create();

    Livewire::test(Subscriptions::class)
        ->assertSee('Subscriptions')
        ->assertSee(['Netflix', 'Crunchyroll', 'Disney+']);
});

test('can see create form', function () {
    Livewire::test(Subscriptions::class)
        ->set('form.name', 'Netflix')
        ->set('form.frequency', BillingFrequency::MONTHLY)
        ->set('form.amount', '9.99')
        ->call('create')
        ->assertSee('Create New Subscription')
        ->assertSet('form.name', '')
        ->assertSet('form.frequency', '')
        ->assertSet('form.amount', '')
        ->assertSet('form.billed_at', '');
});

test('can store subscription', function () {
    Livewire::test(Subscriptions::class)
        ->assertSee('Create')
        ->set('form.name', 'Netflix')
        ->set('form.frequency', BillingFrequency::MONTHLY)
        ->set('form.amount', '9.99')
        ->set('form.billed_at', now())
        ->call('save')
        ->assertSet('form.name', '')
        ->assertSet('form.frequency', '')
        ->assertSet('form.amount', '')
        ->assertSet('form.billed_at', '');

    $subscription = Subscription::firstWhere('name', 'Netflix');

    expect($subscription->frequency)->toBe(BillingFrequency::MONTHLY)
        ->and($subscription->amount)->toBe(9.99);
});

test('can see edit form', function () {
    $subscription = Subscription::factory()->create([
        'name' => 'Netflix',
        'frequency' => BillingFrequency::MONTHLY,
        'amount' => '9.99',
        'billed_at' => now(),
    ]);

    Livewire::test(Subscriptions::class)
        ->call('edit', $subscription->id)
        ->assertSee('Edit Subscription')
        ->assertSet('form.name', 'Netflix')
        ->assertSet('form.frequency', BillingFrequency::MONTHLY)
        ->assertSet('form.amount', '9.99');
});

test('can update subscription', function () {
    $subscription = Subscription::factory()->create([
        'name' => 'Netflix',
        'frequency' => BillingFrequency::MONTHLY,
        'amount' => '9.99',
        'billed_at' => now(),
    ]);

    Livewire::test(Subscriptions::class)
        ->assertSee('Netflix')
        ->call('edit', $subscription->id)
        ->set('form.frequency', BillingFrequency::MONTHLY)
        ->set('form.amount', '15.99')
        ->set('form.billed_at', now()->addDay())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->assertSet('form.frequency', '')
        ->assertSet('form.amount', '')
        ->assertSet('form.billed_at', '');

    tap($subscription->fresh(), function ($subscription) {
        expect($subscription->amount)->toBe(15.99);
    });
});

test('can delete bin', function () {
    $subscription = Subscription::factory()->create([
        'name' => 'Netflix',
        'frequency' => BillingFrequency::MONTHLY,
        'amount' => '9.99',
        'billed_at' => now(),
    ]);

    Livewire::test(Subscriptions::class)
        ->assertSee('Netflix')
        ->call('delete', $subscription->id)
        ->assertDontSee('Netflix');

    expect($subscription->fresh())->toBeNull();
});
