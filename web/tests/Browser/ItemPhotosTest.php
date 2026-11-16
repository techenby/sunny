<?php

use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

test('people can flip through an item\'s photos and choose the primary one', function () {
    config(['filesystems.default' => 'local']);
    Storage::fake('local', ['serve' => true]);
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Camping tent']);

    foreach (['front', 'back', 'scratch'] as $position => $name) {
        Storage::put("teams/1/items/{$name}.jpg", UploadedFile::fake()->image("{$name}.jpg", 1200, 800)->get());
        ItemPhoto::factory()->for($item)->create(['path' => "teams/1/items/{$name}.jpg", 'position' => $position]);
    }

    [$front, $back, $scratch] = $item->photos()->get();

    actingAs($user);

    $page = visit(route('inventory.show', $item, absolute: false));

    $page->assertNoJavaScriptErrors()
        ->assertSee('1 of 3')
        ->assertDontSee('2 of 3')
        ->click('[aria-label="Show photo 3"]')
        ->assertSee('3 of 3')
        ->assertDontSee('1 of 3')
        ->click('[wire\\:key="photo-viewer-' . $scratch->id . '"] [aria-label="Next photo"]')
        ->assertSee('1 of 3')
        ->click('[aria-label="Show photo 2"]')
        ->assertSee('2 of 3')
        ->click('Make primary')
        ->assertPresent('[wire\\:click="makePrimary(' . $front->id . ')"]')
        ->assertSee('1 of 3')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'item-photos');

    expect($item->photos()->pluck('id')->all())->toBe([$back->id, $front->id, $scratch->id]);
});
