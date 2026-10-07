<?php

use App\Enums\TokenLifetime;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API tokens settings')] class extends Component {
    public string $name = '';

    public string $expiration = TokenLifetime::NinetyDays->value;

    public ?string $plainTextToken = null;

    public function createToken(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'expiration' => ['required', Rule::enum(TokenLifetime::class)],
        ]);

        $this->plainTextToken = Auth::user()
            ->createToken($this->name, ['*'], TokenLifetime::from($this->expiration)->expiresAt())
            ->plainTextToken;

        $this->reset('name', 'expiration');

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

    public function disconnectApp(string $clientId): void
    {
        Passport::token()->newQuery()
            ->where('user_id', Auth::id())
            ->where('client_id', $clientId)
            ->where('revoked', false)
            ->each(function (Token $token): void {
                $token->revoke();
                $token->refreshToken?->revoke();
            });

        unset($this->connectedApps);

        Flux::modals()->close();

        Flux::toast(variant: 'success', text: __('App disconnected.'));
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

    /**
     * @return Collection<int, array{client_id: string, name: string, connected_at: \Carbon\CarbonInterface}>
     */
    #[Computed]
    public function connectedApps(): Collection
    {
        return Passport::token()->newQuery()
            ->where('user_id', Auth::id())
            ->where('revoked', false)
            ->with('client')
            ->get()
            ->filter(fn (Token $token): bool => $token->client !== null && ! $token->client->revoked)
            ->groupBy('client_id')
            ->map(fn (Collection $tokens): array => [
                'client_id' => (string) $tokens->first()->client_id,
                'name' => $tokens->first()->client->name,
                'connected_at' => $tokens->min('created_at'),
            ])
            ->sortBy('name')
            ->values();
    }
}; ?>

<section class="w-full">
    @include('pages.settings.heading')

    <flux:heading class="sr-only">{{ __('API tokens settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('API tokens')" :subheading="__('Connect AI assistants to Sunny, and create tokens for the HTTP API')">
        <form wire:submit="createToken" class="my-6 flex w-full items-end gap-2">
            <flux:input wire:model="name" :label="__('Token name')" type="text" :placeholder="__('e.g. Home automation')" class="flex-1" data-test="token-name-input" />

            <flux:select wire:model="expiration" :label="__('Expires')" variant="listbox" class="max-w-40" data-test="token-expiration-select">
                @foreach (TokenLifetime::cases() as $lifetime)
                    <flux:select.option :value="$lifetime->value">{{ $lifetime->getLabel() }}</flux:select.option>
                @endforeach
            </flux:select>

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
                            <flux:table.column>{{ __('Expires') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->tokens as $token)
                                <flux:table.row wire:key="token-{{ $token->id }}">
                                    <flux:table.cell variant="strong">{{ $token->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $token->created_at->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($token->expires_at?->isPast())
                                            <flux:badge size="sm" color="red">{{ __('Expired') }}</flux:badge>
                                        @else
                                            {{ $token->expires_at?->diffForHumans() ?? __('Never') }}
                                        @endif
                                    </flux:table.cell>
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
                <flux:heading>{{ __('Connected apps') }}</flux:heading>

                @if ($this->connectedApps->isEmpty())
                    <flux:text class="mt-3">{{ __("You haven't connected any apps yet.") }}</flux:text>
                @else
                    <flux:table class="mt-3">
                        <flux:table.columns>
                            <flux:table.column>{{ __('App') }}</flux:table.column>
                            <flux:table.column>{{ __('Connected') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->connectedApps as $app)
                                <flux:table.row wire:key="app-{{ $app['client_id'] }}">
                                    <flux:table.cell variant="strong">{{ $app['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $app['connected_at']->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:modal.trigger name="disconnect-app-{{ $app['client_id'] }}">
                                            <flux:button variant="danger" size="sm" data-test="disconnect-app-button">
                                                {{ __('Disconnect') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>

                    @foreach ($this->connectedApps as $app)
                        <flux:modal name="disconnect-app-{{ $app['client_id'] }}" class="max-w-lg" wire:key="disconnect-app-modal-{{ $app['client_id'] }}">
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">{{ __('Disconnect ":name"?', ['name' => $app['name']]) }}</flux:heading>

                                    <flux:subheading>
                                        {{ __('It will immediately lose access to your account. You can connect it again later.') }}
                                    </flux:subheading>
                                </div>

                                <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                                    <flux:modal.close>
                                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                    </flux:modal.close>

                                    <flux:button variant="danger" wire:click="disconnectApp('{{ $app['client_id'] }}')" data-test="confirm-disconnect-app-button">
                                        {{ __('Disconnect') }}
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

                <flux:text class="mt-3">{{ __('The MCP server is at:') }}</flux:text>

                <div class="mt-2">
                    <flux:input :value="url('/mcp')" readonly copyable />
                </div>

                <flux:text class="mt-3">{{ __("Add it as a custom connector or MCP server in Claude, ChatGPT, Raycast, Claude Code, or any app that supports OAuth. You'll be asked to log in to Sunny and allow access, and the app will then appear under Connected apps.") }}</flux:text>

                <flux:text class="mt-3">{{ __('API tokens only work with the HTTP API, not the MCP server.') }}</flux:text>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
