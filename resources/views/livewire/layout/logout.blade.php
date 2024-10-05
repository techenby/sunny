<?php

use App\Livewire\Actions\Logout;

$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/', navigate: true);
};

?>

<flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">Logout</flux:menu.item>
