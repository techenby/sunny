<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('Subscriptions') }}</flux:heading>
        <flux:spacer />
        <flux:button wire:click="create">Create</flux:button>
    </header>

    <section class="space-y-3">
        <div class="flex gap-4">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm"
                placeholder="Search Bins" />
            <flux:spacer />
            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->subscriptions">
            <flux:columns>
                <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                    wire:click="sort('name')">Name</flux:column>
                <flux:column>Frequency</flux:column>
                <flux:column sortable :sorted="$sortBy === 'amount'" :direction="$sortDirection"
                wire:click="sort('amount')">Amount</flux:column>
                <flux:column>Billed At</flux:column>
                <flux:column>Due At</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->subscriptions as $subscription)
                    <flux:row :key="$subscription->id">
                        <flux:cell>{{ $subscription->name }}</flux:cell>
                        <flux:cell>{{ $subscription->frequency }}</flux:cell>
                        <flux:cell>${{ $subscription->amount }}</flux:cell>
                        <flux:cell>{{ $subscription->billed_at }}</flux:cell>
                        <flux:cell>{{ $subscription->due_at }}</flux:cell>
                        <flux:cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $subscription->id }})">Edit</flux:menu.item>
                                    <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $subscription->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:cell>
                    </flux:row>
                @endforeach
            </flux:rows>
        </flux:table>
    </section>

    <flux:modal name="subscription-form" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Subscription</flux:heading>
            </div>

            <flux:input type="text" wire:model="form.name" :label="__('Name')" />
            <flux:input type="text" wire:model="form.frequency" :label="__('Frequency')" />
            <flux:input type="text" wire:model="form.amount" :label="__('Amount')" icon="currency-dollar" />
            <flux:input type="date" wire:model="form.billed_at" :label="__('Billed At')" />
            <flux:input type="date" wire:model="form.due_at" :label="__('Due At')" />
            <flux:textarea wire:model="form.notes" :label="__('Notes')" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>
