@teleport('body')
<flux:modal name="qr-code" class="md:w-96">
    <div class="space-y-6">
        <div class="space-y-1">
            <flux:heading size="lg">{{ __('QR Code') }}</flux:heading>

            <flux:text>{{ $qrCode['name'] ?? '' }}</flux:text>
            <flux:link :href="$qrCode['url'] ?? ''">{{ $qrCode['url'] ?? '' }}</flux:link>
        </div>

        <div class="flex justify-center">
            {!! $qrCode['svg'] ?? '' !!}
        </div>
    </div>
</flux:modal>
@endteleport
