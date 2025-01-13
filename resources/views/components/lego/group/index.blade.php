@props(['level' => 2, 'group', 'list', 'parts'])

<div class="relative">
    <x-lego.group.heading :$level :$group />

    @if ($group->has_parts)
    <x-lego.group.parts :$group :$parts />
    @else
    @foreach ($list->where('parent_id', $group->id) as $child)
    <x-lego.group :level="$level + 1" :list="$list" :group="$child" :$parts />
    @endforeach
    @endif
</div>
