<div wire:poll.2s="check" class="w-full max-w-xl">
    <flux:card class="text-center">
        <flux:heading size="xl">{{ __('Pair this display') }}</flux:heading>
        <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400">
            {{ __('Scan the QR code with your phone, or visit the URL and enter the code below.') }}
        </flux:text>

        <flux:card class="p-1 mx-auto mt-6 inline-flex">
            {!! $this->qrSvg !!}
        </flux:card>

        <div class="mt-6 flex flex-col items-center gap-2">
            <flux:text class="text-xs uppercase tracking-widest text-zinc-500 dark:text-zinc-400">
                {{ __('Pairing code') }}
            </flux:text>
            <flux:heading size="xl" class="font-mono tracking-[0.4em]">{{ $pairingCode }}</flux:heading>
        </div>

        <flux:text class="mt-6 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Waiting for confirmation') }}<x-ui.dotx3 />
        </flux:text>
    </flux:card>
</div>
