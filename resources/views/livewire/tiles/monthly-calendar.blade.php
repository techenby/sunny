<?php

use function Livewire\Volt\{computed, mount, state};

state([
    'position' => null,
    'carbon' => null,
    'daysInMonth' => '',
    'dayOfWeek' => '',
    'month' => '',
    'todaysDate' => '',
]);

mount(function () {
    $this->setUpMonth(now('America/Chicago'));
});

$setUpMonth = function ($carbon = null) {
    $this->carbon = $carbon ?? now('America/Chicago');
    $this->daysInMonth = $this->carbon->daysInMonth;
    $this->dayOfWeek = $this->carbon->copy()->startOfMonth()->dayOfWeek;
    $this->month = $this->carbon->englishMonth;
    $this->todaysDate = $this->carbon->day;
};

$nextMonth = function () {
    $this->setUpMonth($this->carbon->addMonth());
};

$prevMonth = function () {
    $this->setUpMonth($this->carbon->subMonth());
};

?>


<x-dashboard-tile :position="$position">
    <div class="grid grid-cols-7 gap-1 w-full" wire:poll.120s="setUpMonth">
        <button wire:click="prevMonth" class="text-lg text-center mb-4">&laquo;</button>
        <div class="col-span-5 uppercase font-bold text-center mb-4">{{ $this->month }}</div>
        <button wire:click="nextMonth" class="text-lg text-center mb-4">&raquo;</button>

        <div abbr="Sunday" scope="col" title="Sunday" class="text-center text-dimmed mb-2">S</div>
        <div abbr="Monday" scope="col" title="Monday" class="text-center text-dimmed mb-2">M</div>
        <div abbr="Tuesday" scope="col" title="Tuesday" class="text-center text-dimmed mb-2">T</div>
        <div abbr="Wednesday" scope="col" title="Wednesday" class="text-center text-dimmed">W</div>
        <div abbr="Thursday" scope="col" title="Thursday" class="text-center text-dimmed">T</div>
        <div abbr="Friday" scope="col" title="Friday" class="text-center text-dimmed">F</div>
        <div abbr="Saturday" scope="col" title="Saturday" class="text-center text-dimmed">S</div>

        @if ($this->dayOfWeek !== 0)
        <div class="col-span-{{ $this->dayOfWeek }}"></div>
        @endif

        @foreach (range(1, $this->daysInMonth) as $date)
        <div class="text-center {{ $date === $this->todaysDate && $this->month === now()->englishMonth  ? 'border border-gray-500 rounded-md text-inverse' : '' }}">{{ $date }}</div>
        @endforeach
    </div>
</x-dashboard-tile>
