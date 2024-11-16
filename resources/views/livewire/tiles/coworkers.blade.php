<?php

use function Livewire\Volt\{computed, state};

state([
    'position' => null,
]);

$coworkers = computed(function () {
    return [
        ['name' => 'Andrew', 'location' => 'Charlotte, NC', 'timezone' => 'America/New_York'],
        ['name' => 'Anthony', 'location' => 'Victoria, CA', 'timezone' => 'America/Vancouver'],
        ['name' => 'Anna', 'location' => 'Portland, OR', 'timezone' => 'America/Los_Angeles'],
        ['name' => 'Guillermo', 'location' => 'Lima, PE', 'timezone' => 'America/Lima'],
        ['name' => 'Jake', 'location' => 'Dallas, TX', 'timezone' => 'America/Chicago'],
        ['name' => 'Jamo', 'location' => 'Denver, CO', 'timezone' => 'America/Denver'],
        ['name' => 'Jeanne', 'location' => 'Chicago, IL', 'timezone' => 'America/Chicago'],
        ['name' => 'Keith', 'location' => 'Charlottesville, VA', 'timezone' => 'America/New_York'],
        ['name' => 'Marcy', 'location' => 'Chicago, IL', 'timezone' => 'America/Chicago'],
        ['name' => 'Mateus', 'location' => 'Ponta Grossa, BR', 'timezone' => 'America/Sao_Paulo'],
        ['name' => 'Matt', 'location' => 'Atlanta, GA', 'timezone' => 'America/New_York'],
        ['name' => 'Molly', 'location' => 'Lansing, MI', 'timezone' => 'America/New_York'],
        ['name' => 'Nico', 'location' => 'Montevideo, UY', 'timezone' => 'America/Montevideo'],
        ['name' => 'Nohemi', 'location' => 'Mexico City, MX', 'timezone' => 'America/Mexico_City'],
        ['name' => 'Omar', 'location' => 'Amman, JO', 'timezone' => 'Asia/Amman'],
        ['name' => 'Tony', 'location' => 'Maceió, BR', 'timezone' => 'America/Maceio'],
    ];
});
?>

<x-dashboard-tile :position="$position" refresh-interval="10">
    <div class="grid grid-rows-auto-auto gap-2 h-full">
        <h1 class="uppercase font-bold">Coworkers</h1>

        <ul class="self-center divide-y-2 divide-canvas overflow-y-scroll h-full -mx-3 px-3">
            @foreach ($this->coworkers as $coworker)
                <li class="py-1">
                    <div class="my-2 flex items-center justify-between space-x-2">
                        <div>
                            <div class="font-bold">{{ $coworker['name'] }}</div>
                            <div class="text-sm text-dimmed">{{ $coworker['location'] }}</div>
                        </div>
                        <div>
                            {{ now()->timezone($coworker['timezone'])->format('g:i A') }}
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</x-dashboard-tile>
