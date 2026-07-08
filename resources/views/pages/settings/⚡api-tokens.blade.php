<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API tokens settings')] class extends Component {
    public string $name = '';

    public ?string $plainTextToken = null;

    public function createToken(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->plainTextToken = Auth::user()->createToken($this->name)->plainTextToken;

        $this->name = '';

        unset($this->tokens);

        Flux::toast(variant: 'success', text: __('Token created.'));
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->where('id', $tokenId)->delete();

        unset($this->tokens);

        Flux::modals()->close();

        Flux::toast(variant: 'success', text: __('Token revoked.'));
    }

    public function dismissPlainTextToken(): void
    {
        $this->plainTextToken = null;
    }

    /**
     * @return Collection<int, \Laravel\Sanctum\PersonalAccessToken>
     */
    #[Computed]
    public function tokens(): Collection
    {
        return Auth::user()->tokens()->latest()->get();
    }
}; ?>

<section class="w-full">
    @include('pages.settings.heading')

    <flux:heading class="sr-only">{{ __('API tokens settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('API tokens')" :subheading="__('Create tokens to connect external apps, like Raycast or Claude Code, to your account')">
        <form wire:submit="createToken" class="my-6 flex w-full items-end gap-2">
            <flux:input wire:model="name" :label="__('Token name')" type="text" :placeholder="__('e.g. Raycast, Claude')" class="flex-1" data-test="token-name-input" />

            <flux:button variant="primary" type="submit" data-test="create-token-button">
                {{ __('Create') }}
            </flux:button>
        </form>

        @if ($plainTextToken)
            <flux:callout variant="success" icon="key" class="mb-6" data-test="plain-text-token-callout">
                <flux:callout.heading>{{ __('Token created') }}</flux:callout.heading>

                <flux:callout.text>
                    {{ __("Copy your new token now — for security, it won't be shown again.") }}
                </flux:callout.text>

                <div class="mt-3">
                    <flux:input :value="$plainTextToken" readonly copyable data-test="plain-text-token-input" />
                </div>

                <x-slot name="controls">
                    <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="dismissPlainTextToken" :aria-label="__('Dismiss')" />
                </x-slot>
            </flux:callout>
        @endif

        <div class="space-y-6">
            <div>
                <flux:heading>{{ __('Active tokens') }}</flux:heading>

                @if ($this->tokens->isEmpty())
                    <flux:text class="mt-3">{{ __("You haven't created any tokens yet.") }}</flux:text>
                @else
                    <flux:table class="mt-3">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('Created') }}</flux:table.column>
                            <flux:table.column>{{ __('Last used') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->tokens as $token)
                                <flux:table.row wire:key="token-{{ $token->id }}">
                                    <flux:table.cell variant="strong">{{ $token->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $token->created_at->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:modal.trigger name="revoke-token-{{ $token->id }}">
                                            <flux:button variant="danger" size="sm" data-test="revoke-token-button">
                                                {{ __('Revoke') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>

                    @foreach ($this->tokens as $token)
                        <flux:modal name="revoke-token-{{ $token->id }}" class="max-w-lg" wire:key="revoke-token-modal-{{ $token->id }}">
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">{{ __('Revoke ":name"?', ['name' => $token->name]) }}</flux:heading>

                                    <flux:subheading>
                                        {{ __('Any apps using this token will immediately lose access. This action cannot be undone.') }}
                                    </flux:subheading>
                                </div>

                                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                                    <flux:modal.close>
                                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                    </flux:modal.close>

                                    <flux:button variant="danger" wire:click="revokeToken({{ $token->id }})" data-test="confirm-revoke-token-button">
                                        {{ __('Revoke token') }}
                                    </flux:button>
                                </div>
                            </div>
                        </flux:modal>
                    @endforeach
                @endif
            </div>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading>{{ __('Connecting to the MCP server') }}</flux:heading>

                <flux:text class="mt-3">{{ __('Use your token as a bearer token to connect to the MCP server at:') }}</flux:text>

                <div class="mt-2">
                    <flux:input :value="url('/mcp')" readonly copyable />
                </div>

                <flux:heading size="sm" class="mt-6">{{ __('Raycast') }}</flux:heading>

                <flux:text class="mt-2">{{ __('In Raycast, open "Manage MCP Servers", add a new server, and use this configuration (replace <token> with your token):') }}</flux:text>

                <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-800"><code>{
    "url": "{{ url('/mcp') }}",
    "headers": {
        "Authorization": "Bearer &lt;token&gt;"
    }
}</code></pre>

                <flux:heading size="sm" class="mt-6">{{ __('Claude Code') }}</flux:heading>

                <flux:text class="mt-2">{{ __('Run this command in your terminal (replace <token> with your token):') }}</flux:text>

                <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-100 p-4 text-sm dark:bg-zinc-800"><code>claude mcp add --transport http sunny {{ url('/mcp') }} --header "Authorization: Bearer &lt;token&gt;"</code></pre>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
