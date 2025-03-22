<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\form;
use function Livewire\Volt\layout;

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    $this->validate();

    $this->form->authenticate();

    Session::regenerate();

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <!-- Email Address -->
        <flux:input wire:model="form.email" :label="__('Email')" type="email" required autofocus autocomplete="username" />

        <!-- Password -->
        <flux:input wire:model="form.password" :label="__('Password')" type="password" required autocomplete="current-password" />

        <!-- Remember Me -->
        <flux:checkbox wire:model="form.remember" :label="__('Remember me')" />

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <flux:button variant="primary" type="submit" class="ms-3">
                {{ __('Log in') }}
            </flux:button>
        </div>
    </form>
</div>
