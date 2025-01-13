@props(['parts', 'group'])

<div class="flex flex-wrap border-b gap-4 border-gray-300 pb-4">
    @foreach ($parts->where('group_id', $group->id) as $part)
    <div class="mt-auto">
        <img src="{{ $part->image }}" style="zoom: 50%; max-width: 1024px; max-height: 1024px;">
        <div style="margin-top: 5px; text-align: left; ">
            <div class="partname">{{ $part->name }}</div>
            <div class="partnum">{{ $part->part_number }}</div>
        </div>
    </div>
    @endforeach
</div>
