@props(['id' => 800])

@php
$component = match(true) {
    $id >= 200 && $id <= 299 => 'weather-icon.cloud-with-lightning-and-rain',
    $id >= 300 && $id <= 399 => 'weather-icon.cloud-with-rain',
    $id >= 500 && $id <= 599 => 'weather-icon.sun-behind-rain-cloud',
    $id >= 600 && $id <= 699 => 'weather-icon.snow-flake',
    $id >= 700 && $id <= 799 => 'weather-icon.fog',
    $id === 801 => 'weather-icon.sun-behind-small-cloud',
    $id === 802 => 'weather-icon.sun-behind-large-cloud',
    $id === 803 || $id === 804 => 'weather-icon.cloud',
    default => 'weather-icon.sun',
}
@endphp

<x-dynamic-component :class="$attributes->get('class')" :component="$component" />
