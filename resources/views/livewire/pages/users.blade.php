@push('head')
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
@endpush
<flux:main class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>

    <section>
        <div class="flex justify-between gap-8 mb-2">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm" placeholder="Search tiles" />

            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>
        <flux:table :paginate="$this->users">
        <flux:columns>
            <flux:column>ID</flux:column>
            <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:column>
            <flux:column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">Email</flux:column>
            <flux:column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection" wire:click="sort('status')">Status</flux:column>
        </flux:columns>

        <flux:rows>
            @foreach ($this->users as $user)
            <flux:row :key="$user->id">
                <flux:cell>{{ $user->id }}</flux:cell>
                <flux:cell>{{ $user->name }}</flux:cell>
                <flux:cell>{{ $user->email }}</flux:cell>
                <flux:cell>{{ $user->status }}</flux:cell>

                <flux:cell>
                    <flux:dropdown>
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                        <flux:menu>
                            <flux:menu.item icon="pencil-square" wire:click="edit({{ $user->id }})">Edit</flux:menu.item>
                            <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $user->id }})">Delete</flux:menu.item>
                            <flux:menu.item icon="code-bracket" wire:click="getToken({{ $user->id }})">GetToken</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </flux:cell>
            </flux:row>
            @endforeach
        </flux:rows>
    </flux:table>
    </section>

    <flux:modal name="edit-user" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Update User</flux:heading>
                <flux:subheading>Make changes to a user's details.</flux:subheading>
            </div>

            <flux:input wire:model="form.name" :label="__('Name')" type="text" required autocomplete="name" />
            <flux:input wire:model="form.email" :label="__('Email')" type="email" required autocomplete="email" />
            <flux:input wire:model="form.status" :label="__('Current Status')" type="text" clearable />

            <flux:field>
                <flux:label>Status List</flux:label>

                <div class="space-y-2">
                    @foreach ($form->status_list as $index => $item)
                    <div class="flex space-x-2">
                        <x-status wire:model="form.status_list.{{ $index }}" />
                        <flux:button variant="subtle" icon="x-mark" wire:click="removeStatusFromList({{ $index }})"/>
                    </div>
                    @endforeach
                    <flux:button size="xs" wire:click="addStatusToList">Add Status</flux:button>
                </div>

                <flux:error name="form.status_list" />
            </flux:field>

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="api-token" class="space-y-6">
        <div>
            <flux:heading size="lg">API Token</flux:heading>
            <flux:subheading>Copy the token below, it will not be shown again.</flux:subheading>
        </div>

        <flux:input wire:model="apiToken" :label="__('API Token')" type="text" copyable />

        <div class="flex">
            <flux:spacer />

            <flux:button wire:click="closeApiToken" variant="primary">Close</flux:button>
        </div>
    </flux:modal>
</flux:main>
