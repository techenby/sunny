<flux:main class="space-y-6 relative">
    @foreach ($groups->whereNull('parent_id') as $group)
        <x-lego.group :list="$groups" :$group :$parts />
    @endforeach

    <flux:card class="fixed top-0 right-6 w-48 h-96 overflow-scroll">
        <ul>
            @foreach ($groups->whereNull('parent_id') as $group)
            <li>
                <a href="#{{ $group->slug }}">{{ $group->name }}</a>
                @if (! $group->has_parts)
                <ul class="ml-2 mb-2">
                    @foreach ($groups->where('parent_id', $group->id) as $child)
                    <li>
                        <a href="#{{ $child->slug }}">{{ $child->name }}</a>
                    </li>
                    @endforeach
                </ul>
                @endif
            </li>
            @endforeach
        </ul>
    </flux:card>
</flux:main>
