@props(['color', 'size' => 'size-6'])

@if ($color->name === 'Assorted Colors')
<img src="{{ asset('assets/color-picker.png') }}" class="{{ $size }} rounded-full">
@else
<div class="relative inline-block {{ $size }} rounded-full ring-2 ring-white dark:ring-zinc-800" style="background: #{{ $color->hex }}"></div>
@endif
