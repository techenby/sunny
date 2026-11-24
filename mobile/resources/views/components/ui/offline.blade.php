@props([
    'extra' => null,
    'ref' => null,
])

<column class="w-full" native:poll="5s">
    <x-ui.alert
        :ref="$ref"
        :ios="\App\Icons\Ios::WifiSlash"
        :android="\App\Icons\Android::WifiOff"
        :title="\App\Support\Offline::TITLE"
        :message="\App\Support\Offline::ADVICE.($extra ? ' '.$extra : '')"
    />
</column>
