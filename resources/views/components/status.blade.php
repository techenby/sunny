<div x-data="{
    status: $wire.entangle('{{ $attributes->get('wire:model') }}'),
    select($event) {
        this.status.emoji = $event.detail.unicode;
        $el.click();
    }
}">
    <flux:input.group>
        <flux:dropdown>
            <flux:button x-text="status.emoji"></flux:button>

            <flux:menu>
                <emoji-picker x-on:emoji-click="select($event)"></emoji-picker>
            </flux:menu>
        </flux:dropdown>

        <flux:input x-model="status.text" placeholder="Doing or Feeling" />
    </flux:input.group>
</div>
