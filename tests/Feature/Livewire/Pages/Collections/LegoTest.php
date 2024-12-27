<?php

use App\Livewire\Pages\Collections\Lego;
use App\Models\LegoBin;
use App\Models\LegoColor;
use App\Models\LegoPiece;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/collections/lego')
        ->assertOk()
        ->assertSee('LEGO');
});

test('can view component', function () {
    LegoBin::factory()
        ->count(3)
        ->state(new Sequence(
            ['type' => 'Gridfinity 1x2'],
            ['type' => 'Gridfinity 1x3'],
            ['type' => 'Gridfinity 2x2'],
        ))
        ->create();

    Livewire::test(Lego::class)
        ->assertSee('LEGO')
        ->assertSee(['Gridfinity 1x2', 'Gridfinity 1x3', 'Gridfinity 2x2']);
});

test('can create bin', function () {
    $piece = LegoPiece::factory()->create();
    $color = LegoColor::factory()->create();

    Livewire::test(Lego::class)
        ->assertSee('Create')
        ->set('form.type', 'Gridfinity 2x2')
        ->set('form.pieces', [$piece->id])
        ->set('form.colors', [$color->id])
        ->call('save')
        ->assertSet('form.type', '')
        ->assertSet('form.pieces', [])
        ->assertSet('form.colors', []);

    $bin = LegoBin::firstWhere('type', 'Gridfinity 2x2');

    expect($bin->pieces)->toHaveCount(1)
        ->and($bin->colors)->toHaveCount(1);
});

test('can edit bin', function () {
    $bin = LegoBin::factory()
        ->has(LegoPiece::factory()->state(['name' => '1x2 Tile']), 'pieces')
        ->create(['type' => 'Gridfinity 2x2']);
    $piece = LegoPiece::factory()
        ->recycle($bin->pieces->first()->group)
        ->create(['name' => '1x1 Tile']);

    Livewire::test(Lego::class)
        ->assertSee('Gridfinity 2x2')
        ->call('edit', $bin->id)
        ->assertSet('form.type', 'Gridfinity 2x2')
        ->set('form.type', 'Gridfinity 1x2')
        ->set('form.pieces', [$piece->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.type', null)
        ->assertSet('form.pieces', [])
        ->assertSet('form.colors', []);

    tap($bin->fresh(), function ($bin) use ($piece) {
        expect($bin->type)->toBe('Gridfinity 1x2');
        expect($bin->pieces->first()->id)->toBe($piece->id);
    });
});

test('can delete bin', function () {
    $bin = LegoBin::factory()->has(LegoPiece::factory(), 'pieces')->create(['type' => 'Gridfinity 1x2']);

    Livewire::test(Lego::class)
        ->assertSee('Gridfinity 1x2')
        ->assertSee($bin->pieces->first()->name) // using to load the relation
        ->call('delete', $bin->id)
        ->assertDontSee('Gridfinity 1x2');

    $this->assertDatabaseMissing('lego_bins', [
        'name' => 'Gridfinity 1x2',
    ]);
    $this->assertDatabaseMissing('lego_bin_piece', [
        'bin_id' => $bin->id,
        'piece_id' => $bin->pieces->first()->id,
    ]);
});
