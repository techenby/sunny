<x-layouts::guest :title="__('What it costs')">
    <div class="max-w-2xl">
        <flux:heading size="xl" level="1">{{ __('What it costs to run Sunny Home') }}</flux:heading>

        <flux:text class="mt-4 text-base">
            {{ __('Sunny Home has no ads and never sells your data, so I pay to keep it running. Here is what that costs each month. If Sunny Home is useful to your household, sponsoring helps cover these costs.') }}
        </flux:text>

        <flux:table class="mt-8">
            <flux:table.columns>
                <flux:table.column>{{ __('Service') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Per month') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach (array_filter($cloud['items'] ?? []) as $name => $cents)
                    <flux:table.row>
                        <flux:table.cell>{{ __('Laravel Cloud: :name', ['name' => $name]) }}</flux:table.cell>
                        <x-ui.table.money-cell :$cents />
                    </flux:table.row>
                @endforeach

                @foreach ($services as $name => $cents)
                    <flux:table.row>
                        <flux:table.cell>{{ $name }}</flux:table.cell>
                        <x-ui.table.money-cell :$cents />
                    </flux:table.row>
                @endforeach

                <flux:table.row>
                    <flux:table.cell variant="strong">{{ __('Total') }}</flux:table.cell>
                    <x-ui.table.money-cell :cents="$totalCents" variant="strong" />
                </flux:table.row>
            </flux:table.rows>
        </flux:table>

        @if ($syncedAt)
            <flux:text size="sm" class="mt-4">
                {{ __('Laravel Cloud costs are for the :from to :to billing period and update automatically each month. Last updated :date.', [
                    'from' => $cloud['period']['from'] ?? '',
                    'to' => $cloud['period']['to'] ?? '',
                    'date' => $syncedAt->toFormattedDateString(),
                ]) }}
            </flux:text>
        @endif

        <div class="mt-8 flex items-center gap-3">
            <flux:button :href="config('costs.sponsor_url')" variant="primary" icon="heart">{{ __('Sponsor Sunny Home') }}</flux:button>
            <flux:button href="https://github.com/techenby/sunny" variant="ghost">{{ __('View the code') }}</flux:button>
        </div>
    </div>
</x-layouts::guest>
