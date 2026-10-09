@props(['heading', 'action' => null, 'href' => null])

<flux:card body="flush" {{ $attributes->class('flex flex-col') }}>
    <flux:card.header>
        <flux:card.heading size="lg">{{ $heading }}</flux:card.heading>
        @if ($action)
            <flux:card.actions>
                <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" :$href wire:navigate>
                    {{ $action }}
                </flux:button>
            </flux:card.actions>
        @endif
    </flux:card.header>

    <flux:card.body class="flex-1">
        {{ $slot }}
    </flux:card.body>
</flux:card>
