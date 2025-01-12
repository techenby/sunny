<form wire:submit="save" class="space-y-6">
    <div>
        <flux:heading size="lg">Bin</flux:heading>
    </div>

    <flux:autocomplete wire:model="form.type" :label="__('Type')">
        <flux:autocomplete.item>Gridfinity 1×1</flux:autocomplete.item>
        <flux:autocomplete.item>Gridfinity 1×2</flux:autocomplete.item>
        <flux:autocomplete.item>Gridfinity 1×3</flux:autocomplete.item>
        <flux:autocomplete.item>Gridfinity 2×2</flux:autocomplete.item>
        <flux:autocomplete.item>Bag</flux:autocomplete.item>
        <flux:autocomplete.item>Bin</flux:autocomplete.item>
        <flux:autocomplete.item>Drawer</flux:autocomplete.item>
    </flux:autocomplete>

    <flux:select wire:model="form.parts" :label="__('Parts')" variant="listbox" searchable multiple
        placeholder="Choose parts...">
        @foreach ($this->parts as $part)
            <flux:option :value="$part->id">
                <div class="flex items-center gap-2">
                    <x-lego.part :$part />
                    <span>{{ $part->name }}</span>
                </div>
            </flux:option>
        @endforeach
    </flux:select>

    <flux:select wire:model="form.colors" :label="__('Colors')" variant="listbox" searchable multiple
        placeholder="Choose colors...">
        @foreach ($this->colors as $color)
            <flux:option :value="$color->id">
                <div class="flex items-center gap-2">
                    <span class="size-4 rounded-full" style="background: #{{ $color->hex }}"></span>
                    <span>{{ $color->name }}</span>
                </div>
            </flux:option>
        @endforeach
    </flux:select>

    <flux:textarea wire:model="form.notes" :label="__('Notes')" />

    <div class="flex">
        <flux:spacer />

        <flux:button type="submit" variant="primary">Save changes</flux:button>
    </div>
</form>
