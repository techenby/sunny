<?php

use App\Models\User;

use function Livewire\Volt\{computed, mount, state};

state([
    'position' => null,
    'email' => '',
    'user' => null,
]);

mount(function () {
    $this->user = User::firstWhere('email', $this->email);
});

$status = computed(function () {
    return $this->user?->fresh()->status ?? 'Cleared';
});

?>

<x-dashboard-tile :position="$position" refresh-interval="10">
    <div class="h-full flex flex-col items-center justify-center">
        <p class="text-sm text-center">{{ $user?->firstName }}'s Status</p>
        <p class="text-4xl font-bold mb-2 text-center dark:text-gray-200">{{ $this->status }}</p>
    </div>
</x-dashboard-tile>
