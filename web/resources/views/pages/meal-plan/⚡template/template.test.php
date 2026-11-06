<?php

use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(Date::parse('2026-11-04 12:00:00', 'America/Chicago'));

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['meals_saved_through' => '2026-11-03']);
    $this->dinner = $this->team->mealSlots()->firstWhere('name', 'Dinner');
});

test('renders successfully', function () {
    actingAs($this->user)
        ->get(route('meal-plan.template'))
        ->assertOk()
        ->assertSee('Breakfast');

    Livewire::actingAs($this->user)
        ->test('pages::meal-plan.template')
        ->assertOk();
})->group('smoke');

test('a meal is added to one weekday of the template', function () {
    Livewire::actingAs($this->user)
        ->test('pages::meal-plan.template')
        ->call('startAdding', $this->dinner->id, 2)
        ->set('meal', 'Tacos')
        ->call('addMeal')
        ->assertHasNoErrors();

    expect(MealTemplateItem::sole())
        ->weekday->toBe(2)
        ->meal_slot_id->toBe($this->dinner->id)
        ->team_id->toBe($this->team->id);
});

test('a weekday outside the week is rejected', function () {
    Livewire::actingAs($this->user)
        ->test('pages::meal-plan.template')
        ->call('removeMeal', $this->dinner->id, 7, 0)
        ->assertNotFound();
});

test('slots can be added, renamed, and reordered', function () {
    $component = Livewire::actingAs($this->user)
        ->test('pages::meal-plan.template')
        ->call('openSlots')
        ->set('newSlot', 'Snack')
        ->call('addSlot');

    $snack = $this->team->mealSlots()->firstWhere('name', 'Snack');

    $component
        ->set("slotNames.{$snack->id}", 'Dessert')
        ->call('renameSlot', $snack->id)
        ->call('moveSlot', $snack->id, -1);

    expect($this->team->mealSlots()->pluck('name')->all())->toBe(['Breakfast', 'Lunch', 'Dessert', 'Dinner']);
});

test('removing a slot clears the template and upcoming days but keeps saved history', function () {
    MealTemplateItem::factory()->for($this->dinner, 'slot')->create(['weekday' => 4]);
    $past = MealPlanSlot::factory()->for($this->dinner, 'slot')->create(['date' => '2026-11-03']);
    $upcoming = MealPlanSlot::factory()->for($this->dinner, 'slot')->create(['date' => '2026-11-05']);

    Livewire::actingAs($this->user)
        ->test('pages::meal-plan.template')
        ->call('removeSlot', $this->dinner->id);

    expect($this->dinner->fresh())->toBeTrashed()
        ->and(MealTemplateItem::count())->toBe(0)
        ->and(MealPlanSlot::pluck('id')->all())->toBe([$past->id]);
});
