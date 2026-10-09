@props(['cents' => 0])

@php
$formatted = '$' . number_format($cents / 100, 2);
@endphp

<flux:table.cell :attributes="$attributes->merge(['align' => 'end', 'class' => 'tabular-nums'])">{{ $formatted }}</flux:table.cell>
