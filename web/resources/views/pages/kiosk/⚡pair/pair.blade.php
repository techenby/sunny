<div class="w-full max-w-md">
    <flux:card >
        @if ($expired)
            <div class="text-center">
                <flux:icon name="exclamation-triangle" class="mx-auto mb-3 size-10 text-amber-500" />
                <flux:heading size="lg">{{ __('Pairing code expired') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Refresh the kiosk display and scan the new code.') }}
                </flux:text>
            </div>
        @elseif ($paired)
            <div class="text-center">
                <flux:icon name="check-circle" class="mx-auto mb-3 size-10 text-emerald-500" />
                <flux:heading size="lg">{{ __('Device paired') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The display will load shortly.') }}</flux:text>
            </div>
        @else
            <flux:heading size="lg">{{ __('Pair this display') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Code') }}: <span class="font-mono tracking-widest">{{ $code }}</span>
            </flux:text>

            <flux:card size="sm" class="mt-4">
                <flux:heading>{{ $this->deviceLabel }}</flux:heading>
                <flux:text variant="subtle">{{ $device->last_ip }}</flux:text>
            </flux:card>

            <form wire:submit="approve" class="mt-5 space-y-4">
                <flux:input
                    wire:model="name"
                    :label="__('Name (optional)')"
                    :placeholder="__('Kitchen display')"
                    maxlength="60"
                />

                @if ($this->teams->count() > 1)
                    <flux:select wire:model="teamId" :label="__('Pair to team')">
                        <option value="">{{ __('Select a team') }}</option>
                        @foreach ($this->teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </flux:select>
                @else
                    <input type="hidden" wire:model="teamId" />
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Pairing to') }} <strong>{{ $this->teams->first()?->name }}</strong>
                    </flux:text>
                @endif

                <div class="flex items-center justify-between gap-3">
                    <flux:button type="button" wire:click="reject" variant="ghost">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Pair display') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:card>
</div>
