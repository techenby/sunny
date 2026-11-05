@use('App\Icons\Android')
@use('App\Icons\Ios')
@use('App\Ui\Card')

<native:top-bar title="QR Code" back />

@if ($path)
    <scroll-view ref="item-qr-code" fill class="bg-theme-background">
        <column class="w-full items-center gap-6 px-4 pt-4 pb-8">
            <column class="{{ Card::classes() }} w-full items-center gap-4 p-6">
                <image
                    ref="item-qr-code-image"
                    :src="$path"
                    :alt="'QR code for '.$name"
                    class="w-[260] h-[260] rounded-lg"
                    :fit="1"
                />
                <column class="w-full items-center gap-1">
                    <x-ui.heading ref="item-qr-code-name" size="lg" class="text-center">{{ $name }}</x-ui.heading>
                    <x-ui.text ref="item-qr-code-url" size="sm" class="text-center">{{ $url }}</x-ui.text>
                </column>
                <column class="w-full gap-3">
                    @include('native.copy-button', ['ref' => 'item-qr-code-copy-name', 'action' => 'copyName', 'label' => 'name', 'copied' => $copied === 'name'])
                    @include('native.copy-button', ['ref' => 'item-qr-code-copy-url', 'action' => 'copyUrl', 'label' => 'link', 'copied' => $copied === 'url'])
                </column>
                @if ($error !== '')
                    <text ref="item-qr-code-error" class="text-center text-sm text-theme-destructive">{{ $error }}</text>
                @endif
            </column>

            <button ref="item-qr-code-share" variant="primary" size="lg" font="semibold" class="w-full" @tap="share">
                Share for printing
            </button>

            <x-ui.text size="sm" class="text-center">
                Send it to a label printer app like Niimbot, or copy the name and link into the app’s label.
            </x-ui.text>
        </column>
    </scroll-view>
@else
    <x-ui.not-found ref="item-qr-code-missing" thing="item" />
@endif
