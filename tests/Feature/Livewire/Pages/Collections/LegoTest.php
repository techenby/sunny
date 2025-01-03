<?php

use App\Livewire\Pages\Collections\Lego;
use App\Models\LegoBin;
use App\Models\LegoColor;
use App\Models\LegoPart;
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
    $part = LegoPart::factory()->create();
    $color = LegoColor::factory()->create();

    Livewire::test(Lego::class)
        ->assertSee('Create')
        ->set('form.type', 'Gridfinity 2x2')
        ->set('form.parts', [$part->id])
        ->set('form.colors', [$color->id])
        ->call('save')
        ->assertSet('form.type', '')
        ->assertSet('form.parts', [])
        ->assertSet('form.colors', []);

    $bin = LegoBin::firstWhere('type', 'Gridfinity 2x2');

    expect($bin->parts)->toHaveCount(1)
        ->and($bin->colors)->toHaveCount(1);
});

test('can edit bin', function () {
    $bin = LegoBin::factory()
        ->has(LegoPart::factory()->state(['name' => '1x2 Tile']), 'parts')
        ->create(['type' => 'Gridfinity 2x2']);
    $part = LegoPart::factory()
        ->recycle($bin->parts->first()->group)
        ->create(['name' => '1x1 Tile']);

    Livewire::test(Lego::class)
        ->assertSee('Gridfinity 2x2')
        ->call('edit', $bin->id)
        ->assertSet('form.type', 'Gridfinity 2x2')
        ->set('form.type', 'Gridfinity 1x2')
        ->set('form.parts', [$part->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.type', null)
        ->assertSet('form.parts', [])
        ->assertSet('form.colors', []);

    tap($bin->fresh(), function ($bin) use ($part) {
        expect($bin->type)->toBe('Gridfinity 1x2');
        expect($bin->parts->first()->id)->toBe($part->id);
    });
});

test('can delete bin', function () {
    $bin = LegoBin::factory()->has(LegoPart::factory(), 'parts')->create(['type' => 'Gridfinity 1x2']);

    Livewire::test(Lego::class)
        ->assertSee('Gridfinity 1x2')
        ->assertSee($bin->parts->first()->name) // using to load the relation
        ->call('delete', $bin->id)
        ->assertDontSee('Gridfinity 1x2');

    $this->assertDatabaseMissing('lego_bins', [
        'name' => 'Gridfinity 1x2',
    ]);
    $this->assertDatabaseMissing('lego_bin_part', [
        'bin_id' => $bin->id,
        'part_id' => $bin->parts->first()->id,
    ]);
});
