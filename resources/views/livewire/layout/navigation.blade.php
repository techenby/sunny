<?php

use App\Livewire\Actions\Logout;

$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/', navigate: true);
};

?>



<div>
    <flux:header container class="bg-white dark:bg-zinc-900 border-b border-zinc-100 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <a href="{{ route('dashboard') }}" wire:navigate class="max-lg:hidden">
            <x-application-logo class="max-lg:hidden block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
        </a>

        <flux:navbar class="-mb-px max-lg:hidden sm:ms-10">
            <flux:navbar.item icon="home" :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navbar.item>
        </flux:navbar>

        <flux:spacer />

        <flux:dropdown position="top" align="start">
            <flux:profile :avatar="'https://unavatar.io/' . auth()->user()->email" :name="auth()->user()->name" />

            <flux:menu>
                <flux:menu.item icon="user" :href="route('profile')" wire:navigate>Profile</flux:menu.item>

                <flux:menu.separator />

                <flux:menu.item icon="arrow-right-start-on-rectangle" wire:click="logout">Logout</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:sidebar stashable sticky class="lg:hidden bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <a href="{{ route('dashboard') }}" wire:navigate>
            <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" :href="route('dashboard')">{{ __('Dashboard') }}</flux:navlist.item>
        </flux:navlist>

        <flux:spacer />
    </flux:sidebar>
</div>
