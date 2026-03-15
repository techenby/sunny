@teleport('body')
<flux:modal name="qr-code" class="md:w-96">
    <div class="space-y-6">
        <div class="space-y-1">
            <flux:heading size="lg">{{ __('QR Code') }}</flux:heading>

            <flux:text>{{ $qrCodeItemName }}</flux:text>
            <flux:link :href="$qrCodeUrl">{{ $qrCodeUrl }}</flux:link>
        </div>

        <div class="flex justify-center">
            {!! $qrCodeSvg !!}
        </div>
    </div>
</flux:modal>
@endteleport
